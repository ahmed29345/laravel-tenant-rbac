<?php

namespace Ahmed29345\TenantRbac\Models;

use Ahmed29345\TenantRbac\PermissionCache;
use Ahmed29345\TenantRbac\TenantRbac;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A global permission such as "invoices.view". Permissions are shared by
 * every tenant; what differs per tenant is which roles grant them.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Permission extends Model
{
    protected $fillable = ['name', 'description'];

    public function getTable(): string
    {
        return TenantRbac::table('permissions');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantRbac::roleModel(),
            TenantRbac::table('role_permission'),
            'permission_id',
            'role_id',
        );
    }

    protected static function booted(): void
    {
        static::updated(function (Permission $permission): void {
            if ($permission->wasChanged('name')) {
                app(PermissionCache::class)->flush();
            }
        });

        static::deleted(function (): void {
            app(PermissionCache::class)->flush();
        });
    }
}
