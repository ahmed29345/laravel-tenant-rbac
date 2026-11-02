<?php

use Ahmed29345\TenantRbac\Models\Permission;
use Ahmed29345\TenantRbac\Models\Role;
use Ahmed29345\TenantRbac\Models\Tenant;
use Ahmed29345\TenantRbac\Resolvers\HeaderResolver;
use Ahmed29345\TenantRbac\Resolvers\RouteParameterResolver;
use Ahmed29345\TenantRbac\Resolvers\SubdomainResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap in your own models here. Custom models should extend the package
    | models so relationships and cache invalidation keep working.
    |
    */

    'models' => [
        'tenant' => Tenant::class,
        'role' => Role::class,
        'permission' => Permission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'tenants' => 'tenants',
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permission' => 'role_permission',
        'tenant_user_role' => 'tenant_user_role',
        'users' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant foreign key
    |--------------------------------------------------------------------------
    |
    | The column that models using the BelongsToTenant trait store their
    | tenant in. A model can override getTenantKeyName() to use another.
    |
    */

    'tenant_foreign_key' => 'tenant_id',

    /*
    |--------------------------------------------------------------------------
    | Tenant resolution
    |--------------------------------------------------------------------------
    |
    | Resolvers are tried in order; the first one that finds an identifier
    | in the request decides the tenant. The identifier is matched against
    | the "identifier" column of the tenants table. Remove the resolvers you
    | do not use, or add your own implementations of ResolvesTenant.
    |
    */

    'resolution' => [
        'resolvers' => [
            RouteParameterResolver::class,
            HeaderResolver::class,
            SubdomainResolver::class,
        ],

        'identifier' => 'slug',

        'route_parameter' => 'tenant',

        'header' => 'X-Tenant',

        // Base domain for subdomain resolution, e.g. "example.com" makes
        // "acme.example.com" resolve to the tenant with identifier "acme".
        'domain' => env('TENANT_RBAC_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data scoping
    |--------------------------------------------------------------------------
    |
    | When strict is true, querying a tenant-owned model while no tenant is
    | set returns no rows instead of every tenant's rows. Use
    | withoutTenantScope() or TenantContext::run() for deliberate access.
    |
    */

    'scope' => [
        'strict' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate integration
    |--------------------------------------------------------------------------
    |
    | Grants abilities that match a permission the user holds in the current
    | tenant, so $user->can('invoices.view') and @can work. Abilities that
    | are not granted fall through to your gates and policies.
    |
    */

    'gate' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Roles and permissions are cached per user and tenant. A null store
    | uses the application's default cache store. The TTL is in seconds.
    |
    */

    'cache' => [
        'store' => env('TENANT_RBAC_CACHE_STORE'),
        'ttl' => 3600,
        'prefix' => 'tenant-rbac',
    ],

];
