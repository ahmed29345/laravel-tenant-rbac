<?php

namespace Ahmed29345\TenantRbac;

use Ahmed29345\TenantRbac\Models\Permission;
use Ahmed29345\TenantRbac\Models\Role;
use Ahmed29345\TenantRbac\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves configured model classes and table names.
 */
final class TenantRbac
{
    /** @return class-string<Tenant> */
    public static function tenantModel(): string
    {
        return config('tenant-rbac.models.tenant', Tenant::class);
    }

    /** @return class-string<Role> */
    public static function roleModel(): string
    {
        return config('tenant-rbac.models.role', Role::class);
    }

    /** @return class-string<Permission> */
    public static function permissionModel(): string
    {
        return config('tenant-rbac.models.permission', Permission::class);
    }

    public static function table(string $name): string
    {
        return config("tenant-rbac.tables.{$name}", $name);
    }

    /**
     * Normalise a tenant model or primary key to the primary key.
     */
    public static function tenantKey(Model|int|string $tenant): int|string
    {
        return $tenant instanceof Model ? $tenant->getKey() : $tenant;
    }
}
