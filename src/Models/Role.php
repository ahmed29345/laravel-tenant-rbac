<?php

namespace Ahmed29345\TenantRbac\Models;

use Ahmed29345\TenantRbac\Exceptions\PermissionNotFound;
use Ahmed29345\TenantRbac\PermissionCache;
use Ahmed29345\TenantRbac\TenantRbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use LogicException;

/**
 * A role owned by one tenant. Two tenants can each have an "admin" role
 * that grants entirely different permissions.
 *
 * Change permissions through givePermissionTo(), revokePermissionTo() and
 * syncPermissions() so cached lookups are invalidated.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 */
class Role extends Model
{
    protected $fillable = ['tenant_id', 'name'];

    public function getTable(): string
    {
        return TenantRbac::table('roles');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRbac::tenantModel(), 'tenant_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantRbac::permissionModel(),
            TenantRbac::table('role_permission'),
            'role_id',
            'permission_id',
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            TenantRbac::table('tenant_user_role'),
            'role_id',
            'user_id',
        );
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeInTenant(Builder $query, Model|int|string $tenant): void
    {
        $query->where($this->qualifyColumn('tenant_id'), TenantRbac::tenantKey($tenant));
    }

    /**
     * @param  Permission|string|iterable<Permission|string>  ...$permissions
     */
    public function givePermissionTo(Permission|string|iterable ...$permissions): static
    {
        $this->permissions()->syncWithoutDetaching($this->permissionIds($permissions));
        $this->flushCache();

        return $this;
    }

    /**
     * @param  Permission|string|iterable<Permission|string>  ...$permissions
     */
    public function revokePermissionTo(Permission|string|iterable ...$permissions): static
    {
        $this->permissions()->detach($this->permissionIds($permissions));
        $this->flushCache();

        return $this;
    }

    /**
     * @param  iterable<Permission|string>  $permissions
     */
    public function syncPermissions(iterable $permissions): static
    {
        $this->permissions()->sync($this->permissionIds([$permissions]));
        $this->flushCache();

        return $this;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->permissions()->where('name', $name)->exists();
    }

    protected static function booted(): void
    {
        static::updating(function (Role $role): void {
            if ($role->isDirty('tenant_id')) {
                throw new LogicException('A role cannot be moved to another tenant; create a new role instead.');
            }
        });

        static::updated(function (Role $role): void {
            if ($role->wasChanged('name')) {
                $role->flushCache();
            }
        });

        static::deleted(function (Role $role): void {
            $role->flushCache();
        });
    }

    protected function flushCache(): void
    {
        app(PermissionCache::class)->flushTenant($this->tenant_id);
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return list<int|string>
     */
    private function permissionIds(array $permissions): array
    {
        $items = Collection::make($permissions)->flatten();

        $ids = $items->filter(fn ($item) => $item instanceof Permission)->map->getKey();
        $names = $items->filter(fn ($item) => is_string($item))->unique()->values();

        if ($names->isNotEmpty()) {
            $found = TenantRbac::permissionModel()::query()->whereIn('name', $names)->pluck('id', 'name');

            if ($missing = $names->diff($found->keys())->all()) {
                throw PermissionNotFound::named($missing);
            }

            $ids = $ids->merge($found->values());
        }

        return $ids->unique()->values()->all();
    }
}
