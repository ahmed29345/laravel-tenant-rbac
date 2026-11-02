<?php

namespace Ahmed29345\TenantRbac\Http\Middleware;

use Ahmed29345\TenantRbac\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a permission in the current tenant.
 *
 *     ->middleware(['auth', 'tenant', 'tenant.permission:invoices.view'])
 *
 * Several permissions, separated by "|" or ",", pass when the user holds
 * any one of them.
 */
final class EnsureTenantPermission
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $tenant = $this->context->get();
        $user = $request->user();

        abort_if($tenant === null || $user === null || ! method_exists($user, 'hasPermissionTo'), 403);

        foreach (explode('|', implode('|', $permissions)) as $permission) {
            if ($user->hasPermissionTo(trim($permission), $tenant)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
