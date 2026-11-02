<?php

namespace Ahmed29345\TenantRbac\Models;

use Ahmed29345\TenantRbac\PermissionCache;
use Ahmed29345\TenantRbac\TenantRbac;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Tenant extends Model
{
    protected $fillable = ['name', 'slug'];

    public function getTable(): string
    {
        return TenantRbac::table('tenants');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(TenantRbac::roleModel(), 'tenant_id');
    }

    /**
     * Users holding at least one role in this tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            TenantRbac::table('tenant_user_role'),
            'tenant_id',
            'user_id',
        )->distinct();
    }

    protected static function booted(): void
    {
        static::deleted(function (Tenant $tenant): void {
            app(PermissionCache::class)->flushTenant($tenant->getKey());
        });
    }
}
