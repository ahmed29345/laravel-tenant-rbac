<?php

namespace Ahmed29345\TenantRbac;

use Ahmed29345\TenantRbac\Contracts\ResolvesTenant;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Runs the configured resolvers in order and loads the matching tenant.
 *
 * The first resolver that finds an identifier decides: if that identifier
 * does not match a tenant, resolution fails rather than falling through to
 * a weaker source such as a header.
 */
final class TenantResolver
{
    /**
     * @param  list<class-string<ResolvesTenant>>  $resolvers
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $resolvers,
        private readonly string $identifierColumn,
    ) {}

    public function resolve(Request $request): ?Model
    {
        $tenantModel = TenantRbac::tenantModel();

        foreach ($this->resolvers as $class) {
            $resolver = $this->container->make($class);
            $identifier = $resolver->resolve($request);

            if ($identifier instanceof Model) {
                return $identifier instanceof $tenantModel ? $identifier : null;
            }

            if ($identifier === null || $identifier === '') {
                continue;
            }

            return $tenantModel::query()
                ->where($this->identifierColumn, $identifier)
                ->first();
        }

        return null;
    }
}
