<?php

namespace BSPDX\Keystone;

use BSPDX\Keystone\Console\Commands\AssignPermissionCommand;
use BSPDX\Keystone\Console\Commands\AssignRoleCommand;
use BSPDX\Keystone\Console\Commands\EnableMultiTenancyCommand;
use BSPDX\Keystone\Console\Commands\MakePermissionCommand;
use BSPDX\Keystone\Console\Commands\MakeRoleCommand;
use BSPDX\Keystone\Console\Commands\UnassignPermissionCommand;
use BSPDX\Keystone\Console\Commands\UnassignRoleCommand;
use BSPDX\Keystone\Http\Middleware\EnsureHasPermission;
use BSPDX\Keystone\Http\Middleware\EnsureHasRole;
use BSPDX\Keystone\Services\AuthorizationService;
use BSPDX\Keystone\Services\CacheService;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\PermissionRegistrar;
use BSPDX\Keystone\Services\PermissionService;
use BSPDX\Keystone\Services\RoleService;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

class KeystoneServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/keystone.php',
            'keystone'
        );

        // Register service interfaces
        $this->app->singleton(
            RoleServiceInterface::class,
            RoleService::class
        );

        $this->app->singleton(
            PermissionServiceInterface::class,
            PermissionService::class
        );

        $this->app->singleton(
            AuthorizationServiceInterface::class,
            AuthorizationService::class
        );

        $this->app->singleton(
            CacheServiceInterface::class,
            CacheService::class
        );

        // Register PermissionRegistrar for Gate integration
        $this->app->singleton(PermissionRegistrar::class);

        // Register convenient aliases
        $this->app->alias(
            RoleServiceInterface::class,
            'keystone.roles'
        );

        $this->app->alias(
            PermissionServiceInterface::class,
            'keystone.permissions'
        );

        $this->app->alias(
            AuthorizationServiceInterface::class,
            'keystone.authorization'
        );

        $this->app->alias(
            CacheServiceInterface::class,
            'keystone.cache'
        );

        $this->app->alias(
            PermissionRegistrar::class,
            'keystone.permission.registrar'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePermissionCommand::class,
                MakeRoleCommand::class,
                AssignRoleCommand::class,
                AssignPermissionCommand::class,
                UnassignRoleCommand::class,
                UnassignPermissionCommand::class,
                EnableMultiTenancyCommand::class,
            ]);
        }

        // Load the management API routes if enabled and not published. The demo
        // web routes (routes/web.php) are an example to publish, never loaded.
        if (config('keystone.load_routes', false) && ! file_exists(base_path('routes/keystone-api.php'))) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/keystone.php' => config_path('keystone.php'),
        ], 'keystone-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'keystone-migrations');

        // Publish Keystone's own seeders (never the dev app's DatabaseSeeder)
        $this->publishes([
            __DIR__.'/../database/seeders/KeystoneSeeder.php' => database_path('seeders/KeystoneSeeder.php'),
            __DIR__.'/../database/seeders/KeystoneDemoUserSeeder.php' => database_path('seeders/KeystoneDemoUserSeeder.php'),
        ], 'keystone-seeders');

        // Publish example routes
        $this->publishes([
            __DIR__.'/../routes/web.php' => base_path('routes/keystone-web.php'),
            __DIR__.'/../routes/api.php' => base_path('routes/keystone-api.php'),
        ], 'keystone-routes');

        // Register middleware aliases. The namespaced ones always; the short
        // ones only when the application has not defined its own.
        $router = $this->app['router'];
        $router->aliasMiddleware('keystone.role', EnsureHasRole::class);
        $router->aliasMiddleware('keystone.permission', EnsureHasPermission::class);

        foreach (['role' => EnsureHasRole::class, 'permission' => EnsureHasPermission::class] as $alias => $middleware) {
            if (! array_key_exists($alias, $router->getMiddleware())) {
                $router->aliasMiddleware($alias, $middleware);
            }
        }

        // Register Keystone's permission check with the Gate whenever it is
        // resolved, in every context (HTTP, queue workers, scheduler, tinker).
        // Registration adds a closure only and never touches the database.
        $this->callAfterResolving(Gate::class, function (Gate $gate, $app) {
            $app->make(PermissionRegistrar::class)->registerPermissions($gate);
        });
    }
}
