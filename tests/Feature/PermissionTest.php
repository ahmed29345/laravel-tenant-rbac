<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Exceptions\NoCurrentTenant;
use Ahmed29345\TenantRbac\Exceptions\PermissionNotFound;
use Ahmed29345\TenantRbac\Models\Permission;
use Ahmed29345\TenantRbac\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class PermissionTest extends TestCase
{
    public function test_permissions_follow_the_roles_held_in_each_tenant(): void
    {
        $acme = $this->tenant('acme');
        $globex = $this->tenant('globex');
        $this->role($acme, 'accountant', ['invoices.view', 'invoices.create']);
        $this->role($globex, 'auditor', ['invoices.view']);

        $user = $this->user()
            ->assignRole('accountant', $acme)
            ->assignRole('auditor', $globex);

        $this->assertTrue($user->hasPermissionTo('invoices.create', $acme));
        $this->assertTrue($user->hasPermissionTo('invoices.view', $globex));
        $this->assertFalse($user->hasPermissionTo('invoices.create', $globex));
    }

    public function test_permissions_in_lists_the_union_of_all_roles(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'accountant', ['invoices.view', 'invoices.create']);
        $this->role($acme, 'manager', ['invoices.view', 'reports.view']);

        $user = $this->user()->assignRole('accountant', $acme)->assignRole('manager', $acme);

        $this->assertSame(
            ['invoices.create', 'invoices.view', 'reports.view'],
            $user->permissionsIn($acme)->all(),
        );
    }

    public function test_permission_models_and_tenant_keys_are_accepted(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'accountant', ['invoices.view']);

        $user = $this->user()->assignRole('accountant', $acme->id);

        $this->assertTrue($user->hasPermissionTo(Permission::where('name', 'invoices.view')->first(), $acme->id));
    }

    public function test_checks_default_to_the_current_tenant(): void
    {
        $acme = $this->tenant('acme');
        $globex = $this->tenant('globex');
        $this->role($acme, 'accountant', ['invoices.view']);

        $user = $this->user()->assignRole('accountant', $acme);

        $this->actAsTenant($acme);
        $this->assertTrue($user->hasPermissionTo('invoices.view'));
        $this->assertTrue($user->hasRole('accountant'));

        $this->actAsTenant($globex);
        $this->assertFalse($user->hasPermissionTo('invoices.view'));
        $this->assertSame([], $user->permissionsIn()->all());
    }

    public function test_checks_without_any_tenant_throw(): void
    {
        $this->expectException(NoCurrentTenant::class);

        $this->user()->hasPermissionTo('invoices.view');
    }

    public function test_granting_an_unknown_permission_throws(): void
    {
        $role = $this->role($this->tenant('acme'), 'accountant');

        $this->expectException(PermissionNotFound::class);

        $role->givePermissionTo('invoices.vew');
    }

    public function test_revoke_and_sync_permissions_on_a_role(): void
    {
        $acme = $this->tenant('acme');
        $role = $this->role($acme, 'accountant', ['invoices.view', 'invoices.create']);
        Permission::create(['name' => 'reports.view']);

        $role->revokePermissionTo('invoices.create');
        $this->assertFalse($role->hasPermission('invoices.create'));

        $role->syncPermissions(['reports.view']);
        $this->assertSame(['reports.view'], $role->permissions()->pluck('name')->all());
    }

    public function test_an_assignment_row_pointing_at_another_tenants_role_grants_nothing(): void
    {
        $acme = $this->tenant('acme');
        $globexAdmin = $this->role($this->tenant('globex'), 'admin', ['invoices.view']);
        $user = $this->user();

        // A hand-written row that bypasses assignRole()'s validation.
        DB::table('tenant_user_role')->insert([
            'tenant_id' => $acme->id,
            'user_id' => $user->id,
            'role_id' => $globexAdmin->id,
        ]);

        $this->assertFalse($user->hasPermissionTo('invoices.view', $acme));
        $this->assertFalse($user->isMemberOf($acme));
    }
}
