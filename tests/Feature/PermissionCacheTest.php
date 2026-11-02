<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Models\Permission;
use Ahmed29345\TenantRbac\Models\Role;
use Ahmed29345\TenantRbac\Models\Tenant;
use Ahmed29345\TenantRbac\PermissionCache;
use Ahmed29345\TenantRbac\Tests\Fixtures\User;
use Ahmed29345\TenantRbac\Tests\TestCase;
use Closure;
use Illuminate\Support\Facades\DB;

class PermissionCacheTest extends TestCase
{
    private Tenant $acme;

    private Role $accountant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->tenant('acme');
        $this->accountant = $this->role($this->acme, 'accountant', ['invoices.view']);
        Permission::create(['name' => 'invoices.create']);

        $this->user = $this->user()->assignRole('accountant', $this->acme);
    }

    public function test_repeated_checks_are_served_from_the_cache(): void
    {
        $this->user->hasPermissionTo('invoices.view', $this->acme);

        $queries = $this->countQueries(function (): void {
            $this->user->hasPermissionTo('invoices.view', $this->acme);
            $this->user->hasPermissionTo('invoices.create', $this->acme);
            $this->user->hasRole('accountant', $this->acme);
            $this->user->permissionsIn($this->acme);
        });

        $this->assertSame(0, $queries);
    }

    public function test_assigning_a_role_refreshes_the_users_permissions(): void
    {
        $this->role($this->acme, 'clerk', ['invoices.create']);
        $this->assertFalse($this->user->hasPermissionTo('invoices.create', $this->acme));

        $this->user->assignRole('clerk', $this->acme);

        $this->assertTrue($this->user->hasPermissionTo('invoices.create', $this->acme));
    }

    public function test_removing_a_role_refreshes_the_users_permissions(): void
    {
        $this->assertTrue($this->user->hasPermissionTo('invoices.view', $this->acme));

        $this->user->removeRole('accountant', $this->acme);

        $this->assertFalse($this->user->hasPermissionTo('invoices.view', $this->acme));
        $this->assertFalse($this->user->isMemberOf($this->acme));
    }

    public function test_changing_a_roles_permissions_refreshes_every_holder(): void
    {
        $colleague = $this->user('Omar')->assignRole('accountant', $this->acme);
        $this->assertFalse($this->user->hasPermissionTo('invoices.create', $this->acme));
        $this->assertFalse($colleague->hasPermissionTo('invoices.create', $this->acme));

        $this->accountant->givePermissionTo('invoices.create');

        $this->assertTrue($this->user->hasPermissionTo('invoices.create', $this->acme));
        $this->assertTrue($colleague->hasPermissionTo('invoices.create', $this->acme));

        $this->accountant->revokePermissionTo('invoices.view');

        $this->assertFalse($this->user->hasPermissionTo('invoices.view', $this->acme));
    }

    public function test_renaming_or_deleting_a_role_refreshes_holders(): void
    {
        $this->assertTrue($this->user->hasRole('accountant', $this->acme));

        $this->accountant->update(['name' => 'bookkeeper']);
        $this->assertTrue($this->user->hasRole('bookkeeper', $this->acme));

        $this->accountant->delete();
        $this->assertFalse($this->user->hasPermissionTo('invoices.view', $this->acme));
    }

    public function test_renaming_or_deleting_a_permission_refreshes_everyone(): void
    {
        $permission = Permission::where('name', 'invoices.view')->first();
        $this->assertTrue($this->user->hasPermissionTo('invoices.view', $this->acme));

        $permission->update(['name' => 'invoices.read']);
        $this->assertTrue($this->user->hasPermissionTo('invoices.read', $this->acme));
        $this->assertFalse($this->user->hasPermissionTo('invoices.view', $this->acme));

        $permission->delete();
        $this->assertFalse($this->user->hasPermissionTo('invoices.read', $this->acme));
    }

    public function test_flushing_one_tenant_leaves_other_tenants_cached(): void
    {
        $globex = $this->tenant('globex');
        $this->role($globex, 'viewer', ['invoices.view']);
        $this->user->assignRole('viewer', $globex);

        $this->user->hasPermissionTo('invoices.view', $this->acme);
        $this->user->hasPermissionTo('invoices.view', $globex);

        $this->app->make(PermissionCache::class)->flushTenant($this->acme->id);

        $this->assertSame(0, $this->countQueries(fn () => $this->user->hasPermissionTo('invoices.view', $globex)));
        $this->assertGreaterThan(0, $this->countQueries(fn () => $this->user->hasPermissionTo('invoices.view', $this->acme)));
    }

    private function countQueries(Closure $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
