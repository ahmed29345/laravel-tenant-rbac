<?php

namespace Ahmed29345\TenantRbac\Concerns;

use Ahmed29345\TenantRbac\Exceptions\NoCurrentTenant;
use Ahmed29345\TenantRbac\Exceptions\RoleNotFound;
use Ahmed29345\TenantRbac\Exceptions\RoleTenantMismatch;
use Ahmed29345\TenantRbac\Models\Permission;
use Ahmed29345\TenantRbac\Models\Role;
use Ahmed29345\TenantRbac\PermissionCache;
use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\TenantRbac;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Gives a user model roles and permissions that differ per tenant.
 *
 * Methods that take an optional $tenant fall back to the current tenant
 * from TenantContext and throw NoCurrentTenant when there is none.
 */
trait HasTenantRoles
{
    /**
     * Every role the user holds, across all tenants.
     */
    public function tenantRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantRbac::roleModel(),
            TenantRbac::table('tenant_user_role'),
            'user_id',
            'role_id',
        )->withPivot('tenant_id')->withTimestamps();
    }

    /**
     * Tenants in which the user holds at least one role.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantRbac::tenantModel(),
            TenantRbac::table('tenant_user_role'),
            'user_id',
            'tenant_id',
        )->distinct();
    }

    public function assignRole(Role|string $role, Model|int|string $tenant): static
    {
        $tenantId = TenantRbac::tenantKey($tenant);

        $this->rolesRelationIn($tenantId)->syncWithoutDetaching([
            $this->findRoleIn($role, $tenantId)->getKey(),
        ]);

        $this->forgetTenantAccess($tenantId);

        return $this;
    }

    public function removeRole(Role|string $role, Model|int|string $tenant): static
    {
        $tenantId = TenantRbac::tenantKey($tenant);

        $this->rolesRelationIn($tenantId)->detach(
            $this->findRoleIn($role, $tenantId)->getKey(),
        );

        $this->forgetTenantAccess($tenantId);

        return $this;
    }

    /**
     * Replace the user's roles in one tenant. Roles in other tenants are untouched.
     *
     * @param  iterable<Role|string>  $roles
     */
    public function syncRoles(iterable $roles, Model|int|string $tenant): static
    {
        $tenantId = TenantRbac::tenantKey($tenant);

        $ids = Collection::make($roles)
            ->map(fn (Role|string $role) => $this->findRoleIn($role, $tenantId)->getKey())
            ->all();

        $this->rolesRelationIn($tenantId)->sync($ids);

        $this->forgetTenantAccess($tenantId);

        return $this;
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    public function rolesIn(Model|int|string|null $tenant = null): EloquentCollection
    {
        return $this->rolesRelationIn($this->tenantIdOrCurrent($tenant))->get();
    }

    /**
     * True when the user holds any of the given roles in the tenant.
     *
     * @param  Role|string|iterable<Role|string>  $roles
     */
    public function hasRole(Role|string|iterable $roles, Model|int|string|null $tenant = null): bool
    {
        $tenantId = $this->tenantIdOrCurrent($tenant);
        $held = $this->tenantAccess($tenantId)['roles'];

        foreach (is_iterable($roles) ? $roles : [$roles] as $role) {
            if ($role instanceof Role) {
                if ((string) $role->tenant_id !== (string) $tenantId) {
                    continue;
                }

                $role = $role->name;
            }

            if (in_array($role, $held, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionTo(Permission|string $permission, Model|int|string|null $tenant = null): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return in_array($name, $this->tenantAccess($this->tenantIdOrCurrent($tenant))['permissions'], true);
    }

    /**
     * Names of every permission the user holds in the tenant.
     *
     * @return Collection<int, string>
     */
    public function permissionsIn(Model|int|string|null $tenant = null): Collection
    {
        return Collection::make($this->tenantAccess($this->tenantIdOrCurrent($tenant))['permissions']);
    }

    /**
     * A user belongs to a tenant when they hold at least one role in it.
     */
    public function isMemberOf(Model|int|string $tenant): bool
    {
        return $this->tenantAccess(TenantRbac::tenantKey($tenant))['roles'] !== [];
    }

    /**
     * The user's role and permission names in a tenant, cached.
     *
     * @return array{roles: list<string>, permissions: list<string>}
     */
    protected function tenantAccess(int|string $tenantId): array
    {
        return app(PermissionCache::class)->remember(
            $this->getKey(),
            $tenantId,
            fn (): array => $this->loadTenantAccess($tenantId),
        );
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    protected function loadTenantAccess(int|string $tenantId): array
    {
        $assignments = TenantRbac::table('tenant_user_role');
        $roles = TenantRbac::table('roles');
        $rolePermission = TenantRbac::table('role_permission');
        $permissions = TenantRbac::table('permissions');

        // Joining on roles.tenant_id as well as the pivot keeps a hand-written
        // pivot row that points at another tenant's role from granting anything.
        $base = fn () => $this->getConnection()->table($assignments)
            ->join($roles, function ($join) use ($assignments, $roles): void {
                $join->on("{$roles}.id", '=', "{$assignments}.role_id")
                    ->on("{$roles}.tenant_id", '=', "{$assignments}.tenant_id");
            })
            ->where("{$assignments}.user_id", $this->getKey())
            ->where("{$assignments}.tenant_id", $tenantId);

        return [
            'roles' => $base()
                ->orderBy("{$roles}.name")
                ->pluck("{$roles}.name")
                ->all(),
            'permissions' => $base()
                ->join($rolePermission, "{$rolePermission}.role_id", '=', "{$roles}.id")
                ->join($permissions, "{$permissions}.id", '=', "{$rolePermission}.permission_id")
                ->distinct()
                ->orderBy("{$permissions}.name")
                ->pluck("{$permissions}.name")
                ->all(),
        ];
    }

    protected function forgetTenantAccess(int|string $tenantId): void
    {
        app(PermissionCache::class)->forgetUser($this->getKey(), $tenantId);
    }

    protected function rolesRelationIn(int|string $tenantId): BelongsToMany
    {
        return $this->tenantRoles()->withPivotValue('tenant_id', $tenantId);
    }

    protected function findRoleIn(Role|string $role, int|string $tenantId): Role
    {
        if ($role instanceof Role) {
            if ((string) $role->tenant_id !== (string) $tenantId) {
                throw RoleTenantMismatch::make($role->name, $role->tenant_id, $tenantId);
            }

            return $role;
        }

        return TenantRbac::roleModel()::query()
            ->inTenant($tenantId)
            ->where('name', $role)
            ->first() ?? throw RoleNotFound::named($role, $tenantId);
    }

    protected function tenantIdOrCurrent(Model|int|string|null $tenant): int|string
    {
        if ($tenant !== null) {
            return TenantRbac::tenantKey($tenant);
        }

        return app(TenantContext::class)->id()
            ?? throw new NoCurrentTenant('Pass a tenant or set a current tenant before checking roles or permissions.');
    }
}
