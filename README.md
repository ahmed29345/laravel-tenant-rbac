# Laravel Tenant RBAC

[![tests](https://github.com/ahmed29345/laravel-tenant-rbac/actions/workflows/tests.yml/badge.svg)](https://github.com/ahmed29345/laravel-tenant-rbac/actions/workflows/tests.yml)

Multi-tenant roles, permissions and data scoping for Laravel 11 and 12.

In B2B SaaS one person often works for several organizations: owner of one,
accountant in another, read-only auditor in a third. This package models that
directly. Roles belong to a tenant, users are given roles per tenant, and every
permission check is answered for the tenant the request is acting as.
Tenant-owned models are filtered automatically, so a query made as one tenant
never returns another tenant's rows, and a write that would cross tenants
throws instead of succeeding.

- Per-tenant roles, global permissions, one user with different roles in each tenant
- `tenant` middleware that resolves the tenant from a route parameter, header or subdomain and checks membership
- `tenant.permission:...` middleware and Gate integration, so `$user->can()` and `@can` work
- `BelongsToTenant` trait: automatic query scoping, automatic `tenant_id`, cross-tenant write protection
- Cached permission lookups with precise invalidation, on any cache store

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require ahmed29345/laravel-tenant-rbac

php artisan vendor:publish --tag=tenant-rbac-migrations
php artisan vendor:publish --tag=tenant-rbac-config   # optional
php artisan migrate
```

Add the trait to your user model:

```php
use Ahmed29345\TenantRbac\Concerns\HasTenantRoles;

class User extends Authenticatable
{
    use HasTenantRoles;
}
```

The migration creates five tables:

| Table              | Purpose                                                        |
| ------------------ | -------------------------------------------------------------- |
| `tenants`          | Organizations, identified by a unique `slug`                   |
| `permissions`      | Global abilities such as `invoices.view`                       |
| `roles`            | Owned by one tenant; names are unique per tenant               |
| `role_permission`  | Which permissions a role grants                                |
| `tenant_user_role` | Which roles a user holds in which tenant                       |

## Usage

```php
use Ahmed29345\TenantRbac\Concerns\BelongsToTenant;
use Ahmed29345\TenantRbac\Models\{Permission, Role, Tenant};

Permission::create(['name' => 'invoices.view']);
Permission::create(['name' => 'invoices.create']);

$acme   = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
$globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

Role::create(['tenant_id' => $acme->id, 'name' => 'accountant'])->givePermissionTo('invoices.view', 'invoices.create');
Role::create(['tenant_id' => $globex->id, 'name' => 'auditor'])->givePermissionTo('invoices.view');

$user->assignRole('accountant', $acme)->assignRole('auditor', $globex);

$user->hasRole('accountant', $acme);                 // true
$user->hasPermissionTo('invoices.create', $acme);    // true
$user->hasPermissionTo('invoices.create', $globex);  // false
$user->permissionsIn($globex);                       // ['invoices.view']

class Invoice extends Model
{
    use BelongsToTenant;   // needs a tenant_id column
}

Route::middleware(['auth', 'tenant'])->prefix('{tenant}')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('tenant.permission:invoices.view');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);  // bound inside the tenant
});

// Inside a request to /acme/invoices:
Invoice::all();                    // Acme's invoices only
Invoice::create(['number' => 7]);  // tenant_id set to Acme
$user->can('invoices.create');     // checked in Acme; @can works the same way
```

`removeRole()`, `syncRoles($roles, $tenant)`, `rolesIn($tenant)`, `tenants()` and
`isMemberOf($tenant)` are also available. Role and permission checks without a
tenant argument use the current tenant and throw `NoCurrentTenant` when none is
set. On roles, use `givePermissionTo()`, `revokePermissionTo()` and
`syncPermissions()`.

## How tenant resolution works

The `tenant` middleware asks each configured resolver in turn:

1. **Route parameter** `{tenant}`, e.g. `/acme/invoices`
2. **Header** `X-Tenant: acme`, for APIs and mobile clients
3. **Subdomain** of `TENANT_RBAC_DOMAIN`, e.g. `acme.example.com`

The first resolver that finds an identifier decides. The identifier is looked up
in the `slug` column (configurable). If no tenant matches, the response is
**404**. If the user is a guest or holds no role in that tenant, the response is
**403**. Otherwise the tenant becomes current in `TenantContext` for the rest of
the request.

The header is supplied by the client, which is fine because the membership check
still applies. The middleware is given priority over `SubstituteBindings`, so
route model binding for tenant-owned models (`{invoice}` above) already runs
inside the tenant scope: another tenant's invoice ID returns 404.

To add your own strategy, such as a custom domain lookup, implement
`Contracts\ResolvesTenant` and add it to `resolution.resolvers` in the config.

Outside HTTP, set the tenant explicitly. Queued jobs do not inherit it, so pass
the tenant ID to the job:

```php
app(TenantContext::class)->run($tenant, fn () => Invoice::where('overdue', true)->get());
```

## Data scoping rules

- While a tenant is current, queries on `BelongsToTenant` models are limited to
  that tenant, including route model binding and bulk `update()`/`delete()`.
- With no current tenant, queries return nothing (`scope.strict`, on by default).
  Opt out per query with `Invoice::withoutTenantScope()` or target one tenant
  with `Invoice::forTenant($tenant)`.
- New models get the current tenant's key. Creating one with no tenant at all
  throws `NoCurrentTenant`.
- While a tenant is current, saving or deleting a model that belongs to, or is
  being moved to, another tenant throws `CrossTenantWrite`. This also covers
  models loaded with `withoutTenantScope()`.
- With no current tenant (seeders, console commands), an explicit `tenant_id`
  is trusted.

The scope applies to Eloquent. Queries built with `DB::table()` or raw SQL are
not filtered.

## Gate integration

A `Gate::before` callback grants any ability that matches a permission the user
holds in the current tenant. If the permission is not held, the callback returns
`null` rather than `false`, so your own gates and policies still decide. Turn it
off with `gate.enabled`.

## Caching

Each user's role and permission names are cached per tenant. Cache keys include
a global version and a per-tenant version. Invalidation bumps a version, which
works on every cache store and does not need tags.

| Change                                                     | Invalidates               |
| ---------------------------------------------------------- | ------------------------- |
| `assignRole`, `removeRole`, `syncRoles`                    | That user in that tenant  |
| Role permissions changed, role renamed or deleted          | Every user in that tenant |
| Permission renamed or deleted                              | Everything                |

If you write the pivot tables directly, call
`app(PermissionCache::class)->flushTenant($tenantId)` or `->flush()` afterwards.

## Configuration

`config/tenant-rbac.php` covers model classes, table names, the tenant foreign
key column, the resolvers and their settings, strict scoping, Gate integration,
and the cache store, TTL and prefix. Each option is documented in the file.

## Testing

```bash
composer install
composer test
composer lint:check
```

The suite uses Orchestra Testbench with in-memory SQLite. CI runs it on
PHP 8.2, 8.3 and 8.4 against Laravel 11 and 12.

## License

MIT. See [LICENSE](LICENSE).
