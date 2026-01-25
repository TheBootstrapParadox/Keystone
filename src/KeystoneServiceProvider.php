<?php

namespace BSPDX\Keystone;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use BSPDX\Keystone\Http\Middleware\EnsureHasRole;
use BSPDX\Keystone\Http\Middleware\EnsureHasPermission;
use BSPDX\Keystone\Http\Middleware\EnsureTwoFactorEnabled;

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
    }
}
