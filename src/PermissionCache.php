<?php

namespace Ahmed29345\TenantRbac;

use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * Caches each user's roles and permissions per tenant.
 *
 * Keys embed a global version and a per-tenant version. Bumping a version
 * invalidates every entry built on it at once, which works on any cache
 * store without needing tag support.
 */
final class PermissionCache
{
    public function __construct(
        private readonly Repository $store,
        private readonly int $ttl,
        private readonly string $prefix,
    ) {}

    /**
     * @param  Closure(): array{roles: list<string>, permissions: list<string>}  $loader
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function remember(int|string $userId, int|string $tenantId, Closure $loader): array
    {
        return $this->store->remember($this->key($userId, $tenantId), $this->ttl, $loader);
    }

    /**
     * Forget one user's entry in one tenant, e.g. after a role assignment.
     */
    public function forgetUser(int|string $userId, int|string $tenantId): void
    {
        $this->store->forget($this->key($userId, $tenantId));
    }

    /**
     * Invalidate every user's entry in a tenant, e.g. after a role changes.
     */
    public function flushTenant(int|string $tenantId): void
    {
        $this->store->forever($this->tenantVersionKey($tenantId), $this->newVersion());
    }

    /**
     * Invalidate every entry, e.g. after a permission is renamed or deleted.
     */
    public function flush(): void
    {
        $this->store->forever($this->globalVersionKey(), $this->newVersion());
    }

    private function key(int|string $userId, int|string $tenantId): string
    {
        $globalKey = $this->globalVersionKey();
        $tenantKey = $this->tenantVersionKey($tenantId);

        $versions = $this->store->many([$globalKey, $tenantKey]);

        return sprintf(
            '%s:%s:tenant:%s:%s:user:%s',
            $this->prefix,
            $versions[$globalKey] ?? '0',
            $tenantId,
            $versions[$tenantKey] ?? '0',
            $userId,
        );
    }

    private function globalVersionKey(): string
    {
        return "{$this->prefix}:version";
    }

    private function tenantVersionKey(int|string $tenantId): string
    {
        return "{$this->prefix}:tenant:{$tenantId}:version";
    }

    private function newVersion(): string
    {
        return bin2hex(random_bytes(6));
    }
}
