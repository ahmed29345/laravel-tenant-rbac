<?php

namespace Ahmed29345\TenantRbac;

use Ahmed29345\TenantRbac\Exceptions\NoCurrentTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Holds the tenant the current request, job or command is acting for.
 *
 * Bound as a scoped instance, so it is reset between requests and jobs
 * on long-running workers such as Octane and the queue.
 */
final class TenantContext
{
    private ?Model $tenant = null;

    public function set(?Model $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Model
    {
        return $this->tenant;
    }

    /**
     * @throws NoCurrentTenant
     */
    public function getOrFail(): Model
    {
        return $this->tenant ?? throw new NoCurrentTenant('No tenant has been set for the current context.');
    }

    public function id(): int|string|null
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Run a callback as the given tenant, restoring the previous tenant afterwards.
     *
     * @template TReturn
     *
     * @param  callable(Model): TReturn  $callback
     * @return TReturn
     */
    public function run(Model $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
