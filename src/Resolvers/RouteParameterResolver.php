<?php

namespace Ahmed29345\TenantRbac\Resolvers;

use Ahmed29345\TenantRbac\Contracts\ResolvesTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Reads the tenant from a route parameter, e.g. /{tenant}/invoices.
 */
final class RouteParameterResolver implements ResolvesTenant
{
    public function resolve(Request $request): Model|int|string|null
    {
        $value = $request->route()?->parameter(config('tenant-rbac.resolution.route_parameter', 'tenant'));

        return $value instanceof Model || is_scalar($value) ? $value : null;
    }
}
