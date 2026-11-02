<?php

namespace Ahmed29345\TenantRbac;

use Ahmed29345\TenantRbac\Concerns\HasTenantRoles;
use Ahmed29345\TenantRbac\Http\Middleware\EnsureTenantPermission;
use Ahmed29345\TenantRbac\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class TenantRbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenant-rbac.php', 'tenant-rbac');

        $this->app->scoped(TenantContext::class);

        $this->app->singleton(TenantResolver::class, fn (Application $app) => new TenantResolver(
            $app,
            $app['config']->get('tenant-rbac.resolution.resolvers', []),
            $app['config']->get('tenant-rbac.resolution.identifier', 'slug'),
        ));

        $this->app->singleton(PermissionCache::class, fn (Application $app) => new PermissionCache(
            $app->make(CacheFactory::class)->store($app['config']->get('tenant-rbac.cache.store')),
            (int) $app['config']->get('tenant-rbac.cache.ttl', 3600),
            $app['config']->get('tenant-rbac.cache.prefix', 'tenant-rbac'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tenant-rbac.php' => config_path('tenant-rbac.php'),
            ], 'tenant-rbac-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'tenant-rbac-migrations');
        }

        $this->registerMiddleware();
        $this->registerGate();
    }

    private function registerMiddleware(): void
    {
        $this->callAfterResolving(Router::class, function (Router $router): void {
            $router->aliasMiddleware('tenant', ResolveTenant::class);
            $router->aliasMiddleware('tenant.permission', EnsureTenantPermission::class);
        });

        // Resolve the tenant before route model binding, so implicitly bound
        // tenant-owned models are looked up inside the tenant scope.
        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel): void {
            if (! $kernel instanceof HttpKernel) {
                return;
            }

            $priority = $kernel->getMiddlewarePriority();

            if (in_array(ResolveTenant::class, $priority, true)) {
                return;
            }

            $index = array_search(SubstituteBindings::class, $priority, true);
            array_splice($priority, $index === false ? count($priority) : $index, 0, [ResolveTenant::class]);

            $kernel->setMiddlewarePriority($priority);
        });
    }

    private function registerGate(): void
    {
        if (! $this->app['config']->get('tenant-rbac.gate.enabled', true)) {
            return;
        }

        $this->callAfterResolving(Gate::class, function (Gate $gate, Application $app): void {
            $gate->before(function ($user, string $ability) use ($app): ?bool {
                $tenant = $app->make(TenantContext::class)->get();

                if ($tenant === null || ! in_array(HasTenantRoles::class, class_uses_recursive($user), true)) {
                    return null;
                }

                // Grant, or stay silent so gates and policies can still decide.
                return $user->hasPermissionTo($ability, $tenant) ? true : null;
            });
        });
    }
}
