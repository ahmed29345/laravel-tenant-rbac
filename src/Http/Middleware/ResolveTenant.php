<?php

namespace Ahmed29345\TenantRbac\Http\Middleware;

use Ahmed29345\TenantRbac\Concerns\HasTenantRoles;
use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for the request and makes it current.
 *
 * Responds 404 when no tenant can be resolved and 403 when the request
 * is not made by a member of the tenant. Registered as "tenant" and
 * prioritised ahead of route model binding, so bound models are already
 * scoped to the tenant.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        abort_if($tenant === null, 404);

        $user = $request->user();

        if ($user !== null && ! in_array(HasTenantRoles::class, class_uses_recursive($user), true)) {
            throw new LogicException(sprintf('[%s] must use the %s trait.', $user::class, HasTenantRoles::class));
        }

        abort_unless($user?->isMemberOf($tenant), 403);

        $this->context->set($tenant);

        return $next($request);
    }
}
