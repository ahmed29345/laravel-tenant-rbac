<?php

namespace Ahmed29345\TenantRbac\Concerns;

use Ahmed29345\TenantRbac\Exceptions\CrossTenantWrite;
use Ahmed29345\TenantRbac\Exceptions\NoCurrentTenant;
use Ahmed29345\TenantRbac\Scopes\TenantScope;
use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\TenantRbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a tenant.
 *
 * - Queries only see rows of the current tenant.
 * - New rows get the current tenant's key automatically.
 * - While a tenant is set, saving or deleting a row that belongs to a
 *   different tenant throws CrossTenantWrite.
 *
 * @method static Builder<static> withoutTenantScope()
 * @method static Builder<static> forTenant(Model|int|string $tenant)
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (Model $model): void {
            $model->assignAndGuardTenant();
        });

        static::deleting(function (Model $model): void {
            $model->guardOriginalTenant();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRbac::tenantModel(), $this->getTenantKeyName());
    }

    public function getTenantKeyName(): string
    {
        return config('tenant-rbac.tenant_foreign_key', 'tenant_id');
    }

    protected function assignAndGuardTenant(): void
    {
        $column = $this->getTenantKeyName();
        $current = app(TenantContext::class)->id();

        if ($this->getAttribute($column) === null) {
            $this->setAttribute($column, $current ?? throw NoCurrentTenant::forModel($this));
        }

        // Without a current tenant (console, seeders, admin jobs) an explicit
        // tenant key is trusted; the guard only applies to tenant context.
        if ($current === null) {
            return;
        }

        if (! $this->sameTenant($this->getAttribute($column), $current)) {
            throw CrossTenantWrite::make($this, $this->getAttribute($column), $current);
        }

        $this->guardOriginalTenant();
    }

    protected function guardOriginalTenant(): void
    {
        $current = app(TenantContext::class)->id();

        if ($current === null || ! $this->exists) {
            return;
        }

        $original = $this->getRawOriginal($this->getTenantKeyName());

        if (! $this->sameTenant($original, $current)) {
            throw CrossTenantWrite::make($this, $original, $current);
        }
    }

    private function sameTenant(mixed $a, int|string $b): bool
    {
        return $a !== null && (string) $a === (string) $b;
    }
}
