<?php

namespace Ahmed29345\TenantRbac\Tests\Feature;

use Ahmed29345\TenantRbac\Http\Middleware\EnsureTenantPermission;
use Ahmed29345\TenantRbac\Http\Middleware\ResolveTenant;
use Ahmed29345\TenantRbac\TenantRbacServiceProvider;
use Ahmed29345\TenantRbac\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_config_and_migrations_are_publishable(): void
    {
        $config = ServiceProvider::pathsToPublish(TenantRbacServiceProvider::class, 'tenant-rbac-config');
        $migrations = ServiceProvider::pathsToPublish(TenantRbacServiceProvider::class, 'tenant-rbac-migrations');

        $this->assertFileExists(array_key_first($config));
        $this->assertDirectoryExists(array_key_first($migrations));
        $this->assertSame(config_path('tenant-rbac.php'), reset($config));
    }

    public function test_middleware_aliases_are_registered(): void
    {
        $aliases = $this->app['router']->getMiddleware();

        $this->assertSame(ResolveTenant::class, $aliases['tenant']);
        $this->assertSame(EnsureTenantPermission::class, $aliases['tenant.permission']);
    }
}
