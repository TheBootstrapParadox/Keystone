<?php

namespace BSPDX\Keystone;

use BSPDX\Keystone\Console\Commands\AssignPermissionCommand;
use BSPDX\Keystone\Console\Commands\AssignRoleCommand;
use BSPDX\Keystone\Console\Commands\ChangePasswordCommand;
use BSPDX\Keystone\Console\Commands\MakePermissionCommand;
use BSPDX\Keystone\Console\Commands\MakeRoleCommand;
use BSPDX\Keystone\Console\Commands\MakeUserCommand;
use BSPDX\Keystone\Console\Commands\UnassignPermissionCommand;
use BSPDX\Keystone\Console\Commands\UnassignRoleCommand;
use BSPDX\Keystone\Http\Middleware\EnsureFeatureEnabled;
use BSPDX\Keystone\Http\Middleware\EnsureHasPermission;
use BSPDX\Keystone\Http\Middleware\EnsureHasRole;
use BSPDX\Keystone\Http\Middleware\EnsureTwoFactorEnabled;
use BSPDX\Keystone\Http\Middleware\RequirePasskey2FA;
use BSPDX\Keystone\Http\Middleware\RequirePasswordConfirm;
use BSPDX\Keystone\Models\Passkey;
use BSPDX\Keystone\Services\AuthorizationService;
use BSPDX\Keystone\Services\CacheService;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\PasskeyService;
use BSPDX\Keystone\Services\PermissionRegistrar;
use BSPDX\Keystone\Services\PermissionService;
use BSPDX\Keystone\Services\RoleService;
use BSPDX\Keystone\View\Components\LoginForm;
use BSPDX\Keystone\View\Components\PasskeyLogin;
use BSPDX\Keystone\View\Components\PasskeyRegister;
use BSPDX\Keystone\View\Components\RegisterForm;
use BSPDX\Keystone\View\Components\TwoFactorChallenge;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Fortify;

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

        config([
            'passkeys.models.passkey'      => Passkey::class,
            'passkeys.relying_party.name'  => config('keystone.passkey.rp_name'),
            'passkeys.relying_party.id'    => config('keystone.passkey.rp_id'),
        ]);

        $this->app->bind(
            \Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction::class,
            \BSPDX\Keystone\Actions\GeneratePasskeyRegisterOptionsAction::class,
        );

        // Register service interfaces
        $this->app->singleton(
            PasskeyServiceInterface::class,
            PasskeyService::class
        );

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
            PasskeyServiceInterface::class,
            'keystone.passkey'
        );

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
                MakeUserCommand::class,
                AssignRoleCommand::class,
                AssignPermissionCommand::class,
                ChangePasswordCommand::class,
                UnassignRoleCommand::class,
                UnassignPermissionCommand::class,
            ]);
        }

        // Load package routes if enabled
        if (config('keystone.load_routes', false)) {
            if (
                ! file_exists(base_path('routes/keystone-web.php')) &&
                ! file_exists(base_path('routes/keystone-api.php'))
            ) {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            }
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'keystone');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/keystone.php' => config_path('keystone.php'),
        ], 'keystone-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'keystone-migrations');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/keystone'),
        ], 'keystone-views');

        // Publish seeders
        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], 'keystone-seeders');

        // Publish example routes
        $this->publishes([
            __DIR__.'/../routes/web.php' => base_path('routes/keystone-web.php'),
            __DIR__.'/../routes/api.php' => base_path('routes/keystone-api.php'),
        ], 'keystone-routes');

        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('keystone.feature', EnsureFeatureEnabled::class);
        $router->aliasMiddleware('role', EnsureHasRole::class);
        $router->aliasMiddleware('permission', EnsureHasPermission::class);
        $router->aliasMiddleware('2fa', EnsureTwoFactorEnabled::class);
        $router->aliasMiddleware('password-confirm', RequirePasswordConfirm::class);
        $router->aliasMiddleware('passkey-2fa', RequirePasskey2FA::class);

        // Register Blade components
        $this->loadViewComponentsAs('keystone', [
            LoginForm::class,
            RegisterForm::class,
            TwoFactorChallenge::class,
            PasskeyRegister::class,
            PasskeyLogin::class,
        ]);

        // Register Fortify two-factor challenge view if Fortify is installed
        // and the binding hasn't already been set by the application
        if (class_exists(Fortify::class) && ! $this->app->bound(TwoFactorChallengeViewResponse::class)) {
            Fortify::twoFactorChallengeView(function () {
                return view('keystone::two-factor-challenge');
            });
        }

        // Register permissions with Laravel Gate
        // This enables @can('permission.name') in Blade and Gate::allows() in controllers
        $this->registerPermissionsWithGate();
    }

    /**
     * Register all permissions with Laravel's Gate system.
     */
    protected function registerPermissionsWithGate(): void
    {
        // Register permissions with Gate
        // Skip only during migrations/install, but allow during tests
        $isRunningTests = $this->app->environment('testing') || $this->app->runningUnitTests();
        $shouldRegister = ! $this->app->runningInConsole() || $isRunningTests;

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
