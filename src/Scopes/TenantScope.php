<?php

namespace Ahmed29345\TenantRbac\Scopes;

use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\TenantRbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries on tenant-owned models to the current tenant.
 *
 * Adds two builder macros:
 *  - withoutTenantScope(): query every tenant's rows on purpose.
 *  - forTenant($tenant): query one specific tenant's rows.
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn($model->getTenantKeyName()), $tenantId);

            return;
        }

        if (config('tenant-rbac.scope.strict', true)) {
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forTenant', function (Builder $builder, Model|int|string $tenant): Builder {
            $model = $builder->getModel();

            return $builder->withoutGlobalScope($this)->where(
                $model->qualifyColumn($model->getTenantKeyName()),
                TenantRbac::tenantKey($tenant),
            );
        });
    }
}
