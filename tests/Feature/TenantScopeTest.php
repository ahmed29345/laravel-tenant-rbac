<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Exceptions\CrossTenantWrite;
use Ahmed29345\TenantRbac\Exceptions\NoCurrentTenant;
use Ahmed29345\TenantRbac\Models\Tenant;
use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\Tests\Fixtures\Invoice;
use Ahmed29345\TenantRbac\Tests\TestCase;

class TenantScopeTest extends TestCase
{
    private Tenant $acme;

    private Tenant $globex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->tenant('acme');
        $this->globex = $this->tenant('globex');

        Invoice::create(['tenant_id' => $this->acme->id, 'number' => 'A-1']);
        Invoice::create(['tenant_id' => $this->acme->id, 'number' => 'A-2']);
        Invoice::create(['tenant_id' => $this->globex->id, 'number' => 'G-1']);
    }

    public function test_a_tenant_only_reads_its_own_rows(): void
    {
        $this->actAsTenant($this->acme);
        $this->assertEqualsCanonicalizing(['A-1', 'A-2'], Invoice::pluck('number')->all());

        $this->actAsTenant($this->globex);
        $this->assertSame(['G-1'], Invoice::pluck('number')->all());
    }

    public function test_another_tenants_row_cannot_be_found_by_id(): void
    {
        $foreignId = Invoice::forTenant($this->globex)->value('id');

        $this->actAsTenant($this->acme);

        $this->assertNull(Invoice::find($foreignId));
        $this->assertSame(0, Invoice::whereKey($foreignId)->count());
    }

    public function test_new_rows_are_stamped_with_the_current_tenant(): void
    {
        $this->actAsTenant($this->globex);

        $invoice = Invoice::create(['number' => 'G-2']);

        $this->assertSame($this->globex->id, $invoice->tenant_id);
        $this->assertTrue($invoice->tenant->is($this->globex));
    }

    public function test_strict_mode_returns_nothing_without_a_tenant(): void
    {
        $this->assertSame(0, Invoice::count());
    }

    public function test_lenient_mode_returns_everything_without_a_tenant(): void
    {
        config()->set('tenant-rbac.scope.strict', false);

        $this->assertSame(3, Invoice::count());
    }

    public function test_the_scope_can_be_bypassed_explicitly(): void
    {
        $this->actAsTenant($this->acme);

        $this->assertSame(3, Invoice::withoutTenantScope()->count());
        $this->assertSame(['G-1'], Invoice::forTenant($this->globex)->pluck('number')->all());
    }

    public function test_creating_without_any_tenant_throws(): void
    {
        $this->expectException(NoCurrentTenant::class);

        Invoice::create(['number' => 'X-1']);
    }

    public function test_a_row_cannot_be_created_for_another_tenant(): void
    {
        $this->actAsTenant($this->acme);

        $this->expectException(CrossTenantWrite::class);

        Invoice::create(['tenant_id' => $this->globex->id, 'number' => 'G-2']);
    }

    public function test_a_row_cannot_be_moved_to_another_tenant(): void
    {
        $this->actAsTenant($this->acme);
        $invoice = Invoice::first();

        $this->expectException(CrossTenantWrite::class);

        $invoice->update(['tenant_id' => $this->globex->id]);
    }

    public function test_another_tenants_row_cannot_be_updated_even_when_loaded_unscoped(): void
    {
        $this->actAsTenant($this->acme);
        $foreign = Invoice::withoutTenantScope()->where('number', 'G-1')->first();

        try {
            $foreign->update(['total' => 999]);
            $this->fail('Expected a CrossTenantWrite exception.');
        } catch (CrossTenantWrite) {
            // Claiming the row for the current tenant is refused as well.
        }

        $this->expectException(CrossTenantWrite::class);

        $foreign->tenant_id = $this->acme->id;
        $foreign->save();
    }

    public function test_another_tenants_row_cannot_be_deleted(): void
    {
        $this->actAsTenant($this->acme);
        $foreign = Invoice::withoutTenantScope()->where('number', 'G-1')->first();

        try {
            $foreign->delete();
            $this->fail('Expected a CrossTenantWrite exception.');
        } catch (CrossTenantWrite) {
            $this->assertSame(1, Invoice::forTenant($this->globex)->count());
        }
    }

    public function test_bulk_updates_and_deletes_stay_inside_the_tenant(): void
    {
        $this->actAsTenant($this->acme);

        Invoice::query()->update(['total' => 100]);
        Invoice::query()->delete();

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, (int) Invoice::forTenant($this->globex)->value('total'));
        $this->assertSame(1, Invoice::forTenant($this->globex)->count());
    }

    public function test_run_acts_as_a_tenant_and_restores_the_previous_one(): void
    {
        $context = $this->app->make(TenantContext::class);
        $context->set($this->acme);

        $numbers = $context->run($this->globex, fn () => Invoice::pluck('number')->all());

        $this->assertSame(['G-1'], $numbers);
        $this->assertTrue($context->get()->is($this->acme));
    }

    public function test_an_explicit_tenant_key_is_trusted_without_a_current_tenant(): void
    {
        $invoice = Invoice::create(['tenant_id' => $this->globex->id, 'number' => 'G-2']);

        $this->assertSame($this->globex->id, $invoice->tenant_id);
    }
}
