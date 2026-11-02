<?php

namespace Ahmed29345\TenantRbac\Resolvers;

use Ahmed29345\TenantRbac\Contracts\ResolvesTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Reads the tenant from a request header, X-Tenant by default.
 *
 * The header is client-controlled; it is safe because the tenant
 * middleware still checks that the user belongs to the tenant.
 */
final class HeaderResolver implements ResolvesTenant
{
    public function resolve(Request $request): Model|int|string|null
    {
        $value = $request->header(config('tenant-rbac.resolution.header', 'X-Tenant'));

        return is_string($value) && $value !== '' ? trim($value) : null;
    }
}
