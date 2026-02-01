<?php

namespace BSPDX\Keystone;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use BSPDX\Keystone\Http\Middleware\EnsureHasRole;
use BSPDX\Keystone\Http\Middleware\EnsureHasPermission;
use BSPDX\Keystone\Http\Middleware\EnsureTwoFactorEnabled;
use BSPDX\Keystone\Services\PermissionRegistrar;

class KeystoneServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/keystone.php',
            'keystone'
        );

        // Register service interfaces
        $this->app->singleton(
            \BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface::class,
            \BSPDX\Keystone\Services\PasskeyService::class
        );

        $this->app->singleton(
            \BSPDX\Keystone\Services\Contracts\RoleServiceInterface::class,
            \BSPDX\Keystone\Services\RoleService::class
        );

        $this->app->singleton(
            \BSPDX\Keystone\Services\Contracts\PermissionServiceInterface::class,
            \BSPDX\Keystone\Services\PermissionService::class
        );

        $this->app->singleton(
            \BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface::class,
            \BSPDX\Keystone\Services\AuthorizationService::class
        );

        $this->app->singleton(
            \BSPDX\Keystone\Services\Contracts\CacheServiceInterface::class,
            \BSPDX\Keystone\Services\CacheService::class
        );

        // Register PermissionRegistrar for Gate integration
        $this->app->singleton(PermissionRegistrar::class);

        // Register convenient aliases
        $this->app->alias(
            \BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface::class,
            'keystone.passkey'
        );

        $this->app->alias(
            \BSPDX\Keystone\Services\Contracts\RoleServiceInterface::class,
            'keystone.roles'
        );

        $this->app->alias(
            \BSPDX\Keystone\Services\Contracts\PermissionServiceInterface::class,
            'keystone.permissions'
        );

        $this->app->alias(
            \BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface::class,
            'keystone.authorization'
        );

        $this->app->alias(
            \BSPDX\Keystone\Services\Contracts\CacheServiceInterface::class,
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
    public function boot(): void {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \BSPDX\Keystone\Console\Commands\MakePermissionCommand::class,
                \BSPDX\Keystone\Console\Commands\MakeRoleCommand::class,
                \BSPDX\Keystone\Console\Commands\MakeUserCommand::class,
                \BSPDX\Keystone\Console\Commands\AssignRoleCommand::class,
                \BSPDX\Keystone\Console\Commands\AssignPermissionCommand::class,
                \BSPDX\Keystone\Console\Commands\ChangePasswordCommand::class,
                \BSPDX\Keystone\Console\Commands\UnassignRoleCommand::class,
                \BSPDX\Keystone\Console\Commands\UnassignPermissionCommand::class,
            ]);
        }

        // Load package routes if enabled
        if (config('keystone.load_routes', false)) {
            if (
                !file_exists(base_path('routes/keystone-web.php')) &&
                !file_exists(base_path('routes/keystone-api.php'))
            ) {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            }
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'keystone');

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/keystone.php' => config_path('keystone.php'),
        ], 'keystone-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'keystone-migrations');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/keystone'),
        ], 'keystone-views');

        // Publish seeders
        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'keystone-seeders');

        // Publish example routes
        $this->publishes([
            __DIR__ . '/../routes/web.php' => base_path('routes/keystone-web.php'),
            __DIR__ . '/../routes/api.php' => base_path('routes/keystone-api.php'),
        ], 'keystone-routes');

        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('role', EnsureHasRole::class);
        $router->aliasMiddleware('permission', EnsureHasPermission::class);
        $router->aliasMiddleware('2fa', EnsureTwoFactorEnabled::class);

        // Register Blade components
        $this->loadViewComponentsAs('keystone', [
            \BSPDX\Keystone\View\Components\LoginForm::class,
            \BSPDX\Keystone\View\Components\RegisterForm::class,
            \BSPDX\Keystone\View\Components\TwoFactorChallenge::class,
            \BSPDX\Keystone\View\Components\PasskeyRegister::class,
            \BSPDX\Keystone\View\Components\PasskeyLogin::class,
        ]);

        // Register Fortify two-factor challenge view if Fortify is installed
        // and the binding hasn't already been set by the application
        if (class_exists(\Laravel\Fortify\Fortify::class) && !$this->app->bound(\Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse::class)) {
            \Laravel\Fortify\Fortify::twoFactorChallengeView(function () {
                return view('keystone::two-factor-challenge');
            });
        }

        // Register permissions with Laravel Gate
        // This enables @can('permission.name') in Blade and Gate::allows() in controllers
        $this->registerPermissionsWithGate();
    }

    /**
     * Register all permissions with Laravel's Gate system.
     *
     * @return void
     */
    protected function registerPermissionsWithGate(): void
    {
        // Register permissions with Gate
        // Skip only during migrations/install, but allow during tests
        $isRunningTests = $this->app->environment('testing') || $this->app->runningUnitTests();
        $shouldRegister = !$this->app->runningInConsole() || $isRunningTests;

        if ($shouldRegister) {
            try {
                $permissionRegistrar = $this->app->make(PermissionRegistrar::class);
                $gate = $this->app->make(Gate::class);

                $permissionRegistrar->registerPermissions($gate);
            } catch (\Exception $e) {
                // Silently fail during package installation or when tables don't exist yet
                // This prevents errors during initial setup
            }
        }
    }
}
