<?php

namespace Ahmed29345\TenantRbac\Tests;

use Ahmed29345\TenantRbac\Models\Permission;
use Ahmed29345\TenantRbac\Models\Role;
use Ahmed29345\TenantRbac\Models\Tenant;
use Ahmed29345\TenantRbac\TenantContext;
use Ahmed29345\TenantRbac\TenantRbacServiceProvider;
use Ahmed29345\TenantRbac\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [TenantRbacServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/database/migrations',
            __DIR__.'/../database/migrations',
        ]);
    }

    protected function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug]);
    }

    protected function user(string $name = 'Sara'): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@example.com']);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function role(Tenant $tenant, string $name, array $permissions = []): Role
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        return Role::create(['tenant_id' => $tenant->id, 'name' => $name])
            ->givePermissionTo($permissions);
    }

    protected function actAsTenant(?Tenant $tenant): void
    {
        $this->app->make(TenantContext::class)->set($tenant);
    }
}
