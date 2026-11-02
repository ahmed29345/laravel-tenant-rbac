<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Models\Tenant;
use Ahmed29345\TenantRbac\Tests\Fixtures\User;
use Ahmed29345\TenantRbac\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

class GateTest extends TestCase
{
    private Tenant $acme;

    private Tenant $globex;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->tenant('acme');
        $this->globex = $this->tenant('globex');
        $this->role($this->acme, 'accountant', ['invoices.view']);
        $this->role($this->globex, 'viewer', ['reports.view']);

        $this->user = $this->user()
            ->assignRole('accountant', $this->acme)
            ->assignRole('viewer', $this->globex);
    }

    public function test_can_checks_permissions_in_the_current_tenant(): void
    {
        $this->actAsTenant($this->acme);
        $this->assertTrue($this->user->can('invoices.view'));
        $this->assertFalse($this->user->can('reports.view'));

        $this->actAsTenant($this->globex);
        $this->assertFalse($this->user->can('invoices.view'));
        $this->assertTrue($this->user->can('reports.view'));
    }

    public function test_nothing_is_granted_without_a_current_tenant(): void
    {
        $this->assertFalse($this->user->can('invoices.view'));
    }

    public function test_blade_can_directive_uses_the_current_tenant(): void
    {
        $this->actingAs($this->user);
        $template = '@can("invoices.view") visible @else hidden @endcan';

        $this->actAsTenant($this->acme);
        $this->assertSame('visible', trim(Blade::render($template)));

        $this->actAsTenant($this->globex);
        $this->assertSame('hidden', trim(Blade::render($template)));
    }

    public function test_abilities_without_a_matching_permission_fall_through_to_gates(): void
    {
        Gate::define('settings.edit', fn (User $user) => $user->name === 'Sara');

        $this->actAsTenant($this->acme);

        $this->assertTrue($this->user->can('settings.edit'));
        $this->assertFalse($this->user('Omar')->can('settings.edit'));
    }
}
