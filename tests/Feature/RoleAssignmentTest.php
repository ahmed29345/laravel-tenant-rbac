<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Exceptions\RoleNotFound;
use Ahmed29345\TenantRbac\Exceptions\RoleTenantMismatch;
use Ahmed29345\TenantRbac\Models\Role;
use Ahmed29345\TenantRbac\Tests\TestCase;
use Illuminate\Database\QueryException;
use LogicException;

class RoleAssignmentTest extends TestCase
{
    public function test_a_user_holds_different_roles_in_different_tenants(): void
    {
        $acme = $this->tenant('acme');
        $globex = $this->tenant('globex');
        $this->role($acme, 'owner');
        $this->role($globex, 'viewer');

        $user = $this->user()
            ->assignRole('owner', $acme)
            ->assignRole('viewer', $globex);

        $this->assertTrue($user->hasRole('owner', $acme));
        $this->assertFalse($user->hasRole('owner', $globex));
        $this->assertTrue($user->hasRole('viewer', $globex));
        $this->assertFalse($user->hasRole('viewer', $acme));
    }

    public function test_roles_with_the_same_name_are_separate_per_tenant(): void
    {
        $acme = $this->tenant('acme');
        $globex = $this->tenant('globex');
        $acmeAdmin = $this->role($acme, 'admin');
        $globexAdmin = $this->role($globex, 'admin');

        $user = $this->user()->assignRole('admin', $acme);

        $this->assertTrue($user->hasRole($acmeAdmin, $acme));
        $this->assertFalse($user->hasRole('admin', $globex));
        $this->assertFalse($user->hasRole($globexAdmin, $acme));
        $this->assertEquals([$acmeAdmin->id], $user->rolesIn($acme)->modelKeys());
    }

    public function test_has_role_accepts_any_of_several_roles(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'billing');

        $user = $this->user()->assignRole('billing', $acme);

        $this->assertTrue($user->hasRole(['owner', 'billing'], $acme));
        $this->assertFalse($user->hasRole(['owner', 'admin'], $acme));
    }

    public function test_a_role_from_another_tenant_cannot_be_assigned(): void
    {
        $acme = $this->tenant('acme');
        $globexAdmin = $this->role($this->tenant('globex'), 'admin');

        $this->expectException(RoleTenantMismatch::class);

        $this->user()->assignRole($globexAdmin, $acme);
    }

    public function test_assigning_an_unknown_role_throws(): void
    {
        $acme = $this->tenant('acme');
        $this->role($this->tenant('globex'), 'admin');

        $this->expectException(RoleNotFound::class);

        $this->user()->assignRole('admin', $acme);
    }

    public function test_assigning_a_role_twice_is_idempotent(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'admin');

        $user = $this->user()->assignRole('admin', $acme)->assignRole('admin', $acme);

        $this->assertCount(1, $user->rolesIn($acme));
    }

    public function test_a_role_can_be_removed(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'admin');
        $this->role($acme, 'billing');

        $user = $this->user()->assignRole('admin', $acme)->assignRole('billing', $acme);
        $user->removeRole('admin', $acme);

        $this->assertFalse($user->hasRole('admin', $acme));
        $this->assertTrue($user->hasRole('billing', $acme));
    }

    public function test_sync_roles_only_touches_the_given_tenant(): void
    {
        $acme = $this->tenant('acme');
        $globex = $this->tenant('globex');
        $this->role($acme, 'admin');
        $this->role($acme, 'billing');
        $this->role($globex, 'admin');

        $user = $this->user()->assignRole('admin', $acme)->assignRole('admin', $globex);
        $user->syncRoles(['billing'], $acme);

        $this->assertEquals(['billing'], $user->rolesIn($acme)->pluck('name')->all());
        $this->assertTrue($user->hasRole('admin', $globex));
    }

    public function test_tenants_lists_each_membership_once(): void
    {
        $acme = $this->tenant('acme');
        $globex = $this->tenant('globex');
        $this->tenant('initech');
        $this->role($acme, 'admin');
        $this->role($acme, 'billing');
        $this->role($globex, 'viewer');

        $user = $this->user()
            ->assignRole('admin', $acme)
            ->assignRole('billing', $acme)
            ->assignRole('viewer', $globex);

        $this->assertEqualsCanonicalizing(['acme', 'globex'], $user->tenants()->pluck('slug')->all());
        $this->assertTrue($user->isMemberOf($acme));
        $this->assertFalse($user->isMemberOf($this->tenant('umbrella')));
    }

    public function test_role_names_are_unique_within_a_tenant(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'admin');
        $this->role($this->tenant('globex'), 'admin');

        $this->expectException(QueryException::class);

        $this->role($acme, 'admin');
    }

    public function test_a_role_cannot_be_moved_to_another_tenant(): void
    {
        $role = $this->role($this->tenant('acme'), 'admin');
        $globex = $this->tenant('globex');

        $this->expectException(LogicException::class);

        $role->update(['tenant_id' => $globex->id]);
    }

    public function test_deleting_a_tenant_removes_its_roles_and_assignments(): void
    {
        $acme = $this->tenant('acme');
        $this->role($acme, 'admin');
        $user = $this->user()->assignRole('admin', $acme);

        $acme->delete();

        $this->assertSame(0, Role::query()->count());
        $this->assertCount(0, $user->tenantRoles()->get());
    }
}
