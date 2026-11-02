<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Models\Tenant;
use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\Tests\Fixtures\Invoice;
use Ahmed29345\TenantRbac\Tests\Fixtures\User;
use Ahmed29345\TenantRbac\Tests\TestCase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    private Tenant $acme;

    private Tenant $globex;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->tenant('acme');
        $this->globex = $this->tenant('globex');
        $this->role($this->acme, 'accountant', ['invoices.view']);
        $this->role($this->acme, 'staff');
        $this->role($this->globex, 'accountant', ['invoices.view', 'invoices.create']);

        $this->member = $this->user()->assignRole('accountant', $this->acme);
    }

    protected function defineRoutes($router): void
    {
        $current = fn () => ['tenant' => app(TenantContext::class)->get()->slug];

        Route::middleware('tenant')->get('/t/{tenant}/dashboard', $current);
        Route::middleware('tenant')->get('/api/me', $current);
        Route::middleware('tenant')->domain('{account}.example.test')->get('/home', $current);

        Route::middleware(['tenant', 'tenant.permission:invoices.view'])
            ->get('/t/{tenant}/invoices', fn () => Invoice::query()->orderBy('number')->pluck('number'));

        Route::middleware(['tenant', 'tenant.permission:invoices.create|invoices.view'])
            ->get('/t/{tenant}/any', fn () => 'ok');

        Route::middleware(['tenant', 'tenant.permission:invoices.create'])
            ->post('/t/{tenant}/invoices', fn () => 'created');

        // SubstituteBindings is listed first on purpose: the package must still
        // resolve the tenant before the invoice is bound.
        Route::middleware([SubstituteBindings::class, 'tenant'])
            ->get('/t/{tenant}/invoices/{invoice}', fn (string $tenant, Invoice $invoice) => $invoice->number);
    }

    public function test_a_member_reaches_the_route_and_the_tenant_becomes_current(): void
    {
        $this->actingAs($this->member)
            ->getJson('/t/acme/dashboard')
            ->assertOk()
            ->assertExactJson(['tenant' => 'acme']);
    }

    public function test_an_unknown_tenant_is_not_found(): void
    {
        $this->actingAs($this->member)->get('/t/nope/dashboard')->assertNotFound();
    }

    public function test_a_user_outside_the_tenant_is_forbidden(): void
    {
        $this->actingAs($this->member)->get('/t/globex/dashboard')->assertForbidden();
    }

    public function test_a_guest_is_forbidden(): void
    {
        $this->get('/t/acme/dashboard')->assertForbidden();
    }

    public function test_the_tenant_can_come_from_a_header(): void
    {
        $this->actingAs($this->member)
            ->getJson('/api/me', ['X-Tenant' => 'acme'])
            ->assertExactJson(['tenant' => 'acme']);

        $this->actingAs($this->member)->getJson('/api/me', ['X-Tenant' => 'globex'])->assertForbidden();
        $this->actingAs($this->member)->getJson('/api/me')->assertNotFound();
    }

    public function test_the_tenant_can_come_from_a_subdomain(): void
    {
        config()->set('tenant-rbac.resolution.domain', 'example.test');

        $this->actingAs($this->member)
            ->getJson('http://acme.example.test/home')
            ->assertExactJson(['tenant' => 'acme']);

        $this->actingAs($this->member)->getJson('http://globex.example.test/home')->assertForbidden();
    }

    public function test_the_route_parameter_wins_over_the_header(): void
    {
        $this->member->assignRole('accountant', $this->globex);

        $this->actingAs($this->member)
            ->getJson('/t/acme/dashboard', ['X-Tenant' => 'globex'])
            ->assertExactJson(['tenant' => 'acme']);
    }

    public function test_the_permission_middleware_requires_the_permission_in_the_current_tenant(): void
    {
        $this->actingAs($this->member)->get('/t/acme/invoices')->assertOk();
        $this->actingAs($this->member)->post('/t/acme/invoices')->assertForbidden();

        $staff = $this->user('Omar')->assignRole('staff', $this->acme);
        $this->actingAs($staff)->get('/t/acme/invoices')->assertForbidden();
    }

    public function test_the_same_user_gets_different_permissions_in_another_tenant(): void
    {
        $this->member->assignRole('accountant', $this->globex);

        $this->actingAs($this->member)->post('/t/acme/invoices')->assertForbidden();
        $this->actingAs($this->member)->post('/t/globex/invoices')->assertOk();
    }

    public function test_any_of_several_permissions_is_enough(): void
    {
        $this->actingAs($this->member)->get('/t/acme/any')->assertOk();
    }

    public function test_routes_only_see_the_current_tenants_rows(): void
    {
        Invoice::create(['tenant_id' => $this->acme->id, 'number' => 'A-1']);
        Invoice::create(['tenant_id' => $this->globex->id, 'number' => 'G-1']);

        $this->actingAs($this->member)->getJson('/t/acme/invoices')->assertExactJson(['A-1']);
    }

    public function test_route_model_binding_is_scoped_to_the_tenant(): void
    {
        $own = Invoice::create(['tenant_id' => $this->acme->id, 'number' => 'A-1']);
        $foreign = Invoice::create(['tenant_id' => $this->globex->id, 'number' => 'G-1']);

        $this->actingAs($this->member)->get("/t/acme/invoices/{$own->id}")->assertOk()->assertSee('A-1');
        $this->actingAs($this->member)->get("/t/acme/invoices/{$foreign->id}")->assertNotFound();
    }
}
