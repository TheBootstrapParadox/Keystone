# Remove Authentication, Keep Multi-Tenant RBAC Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `bspdx/keystone` into a backend-only, multi-tenant RBAC package by removing all authentication orchestration (Fortify integration, Sanctum, passkeys, TOTP 2FA, password confirmation, account deletion, all Blade UI) while keeping roles, permissions, and tenant scoping intact.

**Architecture:** Strip `HasKeystone` down to role/permission/tenant methods only (no more `HasApiTokens`/`TwoFactorAuthenticatable`/`InteractsWithPasskeys`), delete every file whose sole purpose is auth orchestration, prune `KeystoneServiceProvider`/`config/keystone.php`/`routes/*` to match, and update the docs/CHANGELOG to describe the new scope. Host apps keep authenticating however they choose (typically Fortify) and add `HasKeystone` directly to their own User model.

**Tech Stack:** PHP 8.2+, Laravel 12/13, PHPUnit, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-08-17-remove-authentication-keep-rbac-design.md`

## Global Constraints

- Single commit: no task below runs `git commit`. Stage and commit everything only in the final task.
- All work happens directly on the current `develop` branch — no new branch, no worktree.
- Next version is **0.10.0**, marked **BREAKING** in `CHANGELOG.md` with a **Breaking Changes** section and a **Migration Guide**, per this repo's CHANGELOG conventions.
- Every deletion below is verified against a grep of the whole tree (`src`, `database`, `tests`, `app`, `routes`, `resources`, `config`, `bootstrap`, `composer.json`) — do not delete anything not explicitly listed, and do not skip anything explicitly listed.

---

### Task 1: Composer dependencies & package metadata

**Files:**
- Modify: `composer.json`

**Interfaces:**
- Produces: a `composer.json` with no `laravel/fortify`, `laravel/sanctum`, `spatie/laravel-passkeys`, or `pragmarx/google2fa-laravel` in `require`. Later tasks assume these packages are gone (no code may reference their namespaces after Task 2).

- [ ] **Step 1: Edit `composer.json`**

Change the `description` field:

```json
    "description": "Multi-tenant role-based access control (RBAC) for Laravel — bring your own authentication",
```

Change the `keywords` array:

```json
    "keywords": [
        "laravel",
        "rbac",
        "permissions",
        "roles",
        "multi-tenant",
        "authorization"
    ],
```

Change the `require` block to drop the four auth dependencies:

```json
    "require": {
        "php": "^8.2.0",
        "laravel/framework": "^12.0|^13.0"
    },
```

- [ ] **Step 2: Verify `composer.json` is valid JSON**

Run: `php -r "json_decode(file_get_contents('composer.json'), flags: JSON_THROW_ON_ERROR); echo 'valid';"`
Expected: `valid`

---

### Task 2: Strip the RBAC-only backend — trait, service provider, and delete every auth-only `src/` file

**Files:**
- Modify: `src/Traits/HasKeystone.php`
- Modify: `src/KeystoneServiceProvider.php`
- Modify: `app/Models/User.php`
- Modify: `bootstrap/providers.php`
- Delete: `src/Models/KeystoneUser.php`
- Delete: `src/Models/Passkey.php`
- Delete: `src/Contracts/HasPasskeys.php`
- Delete: `src/Http/Controllers/LoginController.php`
- Delete: `src/Http/Controllers/PasskeyAuthController.php`
- Delete: `src/Http/Controllers/TwoFactorAuthController.php`
- Delete: `src/Http/Controllers/AccountDeletionController.php`
- Delete: `src/Http/Controllers/ProfileController.php`
- Delete: `src/Http/Controllers/Concerns/ThrottlesAuthentication.php`
- Delete: `src/Http/Middleware/RequirePasswordConfirm.php`
- Delete: `src/Http/Middleware/EnsureTwoFactorEnabled.php`
- Delete: `src/Http/Middleware/RequirePasskey2FA.php`
- Delete: `src/Services/Contracts/PasskeyServiceInterface.php`
- Delete: `src/Services/PasskeyService.php`
- Delete: `src/Support/PasskeyConfig.php`
- Delete: `src/Actions/GeneratePasskeyRegisterOptionsAction.php`
- Delete: `src/Console/Commands/MakeUserCommand.php`
- Delete: `src/Console/Commands/ChangePasswordCommand.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `HasKeystone` trait exposing only `scopeRole()`, `roles()`, `permissions()`,
  `assignRole()`, `removeRole()`, `syncRoles()`, `givePermissionTo()`, `revokePermissionTo()`,
  `syncPermissions()`, `hasRole()`, `hasAnyRole()`, `hasAllRoles()`, `hasPermissionTo()`,
  `hasPermissionViaRole()`, `getAllPermissions()`, `hasAnyPermission()`, `hasAllPermissions()`,
  `hasDirectPermission()`, `convertToRoleModels()`, `convertToPermissionModels()`,
  `forgetCachedPermissions()`, `isSuperAdmin()`, `canBypassPermissions()`. `KeystoneServiceProvider`
  no longer binds `PasskeyServiceInterface`, no longer registers passkey/2FA/password-confirm
  middleware aliases, no longer registers Blade components. Later tasks (config, routes, tests,
  docs) assume all of the files listed above under "Delete" no longer exist.

- [ ] **Step 1: Replace `src/Traits/HasKeystone.php` in full**

```php
<?php

namespace BSPDX\Keystone\Traits;

use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasKeystone
{
    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Scope a query to users with the given role.
     */
    public function scopeRole(Builder $query, string|array $roles): Builder {
        return $query->whereHas('roles', function ($q) use ($roles) {
            $q->whereIn('name', (array) $roles);
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * User's roles relationship with tenant filtering
     */
    public function roles(): MorphToMany
    {
        $relation = $this->morphToMany(
            KeystoneRole::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        )->withPivot('tenant_id')->withTimestamps();

        // Apply tenant filtering on pivot table
        if (config('keystone.features.multi_tenant', false)) {
            if ($this->tenant_id) {
                // User has a tenant: show both tenant-specific and global roles
                $relation->wherePivotIn('tenant_id', [$this->tenant_id, null]);
            }
            // If user has no tenant (tenant_id = NULL), show all roles (no filtering needed)
            // This allows users without tenants to have roles in non-multi-tenant scenarios
        }

        return $relation;
    }

    /**
     * User's direct permissions (not via roles)
     */
    public function permissions(): MorphToMany
    {
        $relation = $this->morphToMany(
            KeystonePermission::class,
            'model',
            'model_has_permissions',
            'model_id',
            'permission_id'
        )->withPivot('tenant_id')->withTimestamps();

        // Apply tenant filtering
        if (config('keystone.features.multi_tenant', false)) {
            if ($this->tenant_id) {
                // User has a tenant: show both tenant-specific and global permissions
                $relation->wherePivotIn('tenant_id', [$this->tenant_id, null]);
            }
            // If user has no tenant (tenant_id = NULL), show all permissions (no filtering needed)
            // This allows users without tenants to have permissions in non-multi-tenant scenarios
        }

        return $relation;
    }

    // ============================================
    // ROLE ASSIGNMENT METHODS
    // ============================================

    /**
     * Assign roles to user with automatic tenant_id population
     */
    public function assignRole(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        $pivotData = [];
        foreach ($roleModels as $role) {
            $pivotData[$role->id] = [
                'tenant_id' => $this->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->roles()->syncWithoutDetaching($pivotData);
        $this->forgetCachedPermissions();
        $this->unsetRelation('roles'); // Force reload of roles relationship

        return $this;
    }

    /**
     * Remove roles from user (tenant-scoped)
     */
    public function removeRole(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        // Only remove roles from current tenant context
        DB::table('model_has_roles')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->whereIn('role_id', $roleModels->pluck('id'))
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        $this->forgetCachedPermissions();
        $this->unsetRelation('roles'); // Force reload of roles relationship

        return $this;
    }

    /**
     * Sync roles for user (tenant-scoped)
     */
    public function syncRoles(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        // Remove all current tenant roles
        DB::table('model_has_roles')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        // Add new roles
        if ($roleModels->isNotEmpty()) {
            $this->assignRole(...$roleModels);
        } else {
            // If removing all roles, still need to unset the relationship
            $this->unsetRelation('roles');
        }

        return $this;
    }

    // ============================================
    // PERMISSION ASSIGNMENT METHODS
    // ============================================

    /**
     * Give direct permissions to user
     */
    public function givePermissionTo(...$permissions): self
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        $pivotData = [];
        foreach ($permissionModels as $permission) {
            $pivotData[$permission->id] = [
                'tenant_id' => $this->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->permissions()->syncWithoutDetaching($pivotData);
        $this->forgetCachedPermissions();
        $this->unsetRelation('permissions'); // Force reload of permissions relationship

        return $this;
    }

    /**
     * Revoke direct permissions from user
     */
    public function revokePermissionTo(...$permissions): self
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        DB::table('model_has_permissions')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->whereIn('permission_id', $permissionModels->pluck('id'))
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        $this->forgetCachedPermissions();
        $this->unsetRelation('permissions'); // Force reload of permissions relationship

        return $this;
    }

    /**
     * Sync direct permissions for user
     */
    public function syncPermissions(...$permissions): self
    {
        DB::table('model_has_permissions')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        $permissionModels = $this->convertToPermissionModels($permissions);
        if ($permissionModels->isNotEmpty()) {
            $this->givePermissionTo(...$permissionModels);
        } else {
            // If removing all permissions, still need to unset the relationship
            $this->unsetRelation('permissions');
        }

        return $this;
    }

    // ============================================
    // PERMISSION CHECKING METHODS
    // ============================================

    /**
     * Check if user has a specific role
     */
    public function hasRole($roles, string $guard = 'web'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (is_string($roles)) {
            return $this->roles
                ->where('guard_name', $guard)
                ->contains('name', $roles);
        }

        if ($roles instanceof KeystoneRole) {
            return $this->roles->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(...$roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo($permission, string $guard = 'web'): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check direct permissions
        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        if ($this->permissions->where('guard_name', $guard)->contains('name', $permissionName)) {
            return true;
        }

        // Check permissions via roles
        return $this->hasPermissionViaRole($permission, $guard);
    }

    /**
     * Check if user has permission via any of their roles
     */
    protected function hasPermissionViaRole($permission, string $guard = 'web'): bool
    {
        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        return $this->roles
            ->where('guard_name', $guard)
            ->flatMap->permissions
            ->where('guard_name', $guard)
            ->contains('name', $permissionName);
    }

    /**
     * Get all permissions for user (direct + via roles)
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;

        $this->roles->each(function ($role) use (&$permissions) {
            $permissions = $permissions->merge($role->permissions);
        });

        return $permissions->unique('id');
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(...$permissions): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(...$permissions): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has a direct permission (not via role)
     */
    public function hasDirectPermission($permission, string $guard = 'web'): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        return $this->permissions
            ->where('guard_name', $guard)
            ->contains('name', $permissionName);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Convert mixed role input to KeystoneRole models
     */
    protected function convertToRoleModels($roles): Collection
    {
        return collect($roles)->flatten()->map(function ($role) {
            if ($role instanceof KeystoneRole) {
                return $role;
            }
            return KeystoneRole::where('name', $role)->firstOrFail();
        });
    }

    /**
     * Convert mixed permission input to KeystonePermission models
     */
    protected function convertToPermissionModels($permissions): Collection
    {
        return collect($permissions)->flatten()->map(function ($permission) {
            if ($permission instanceof KeystonePermission) {
                return $permission;
            }
            return KeystonePermission::where('name', $permission)->firstOrFail();
        });
    }

    /**
     * Clear cached permissions for this user
     */
    protected function forgetCachedPermissions(): void
    {
        Cache::forget("user_permissions_{$this->id}");
    }

    // ============================================
    // ADMIN METHODS
    // ============================================

    /**
     * Determine if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        $superAdminRole = config('keystone.rbac.super_admin_role', 'super-admin');

        return $this->roles->contains('name', $superAdminRole);
    }

    /**
     * Check if user can bypass permission checks (super admin).
     */
    public function canBypassPermissions(): bool
    {
        return $this->isSuperAdmin();
    }
}
```

- [ ] **Step 2: Replace `src/KeystoneServiceProvider.php` in full**

```php
<?php

namespace BSPDX\Keystone;

use BSPDX\Keystone\Console\Commands\AssignPermissionCommand;
use BSPDX\Keystone\Console\Commands\AssignRoleCommand;
use BSPDX\Keystone\Console\Commands\MakePermissionCommand;
use BSPDX\Keystone\Console\Commands\MakeRoleCommand;
use BSPDX\Keystone\Console\Commands\UnassignPermissionCommand;
use BSPDX\Keystone\Console\Commands\UnassignRoleCommand;
use BSPDX\Keystone\Http\Middleware\EnsureFeatureEnabled;
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

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/keystone.php' => config_path('keystone.php'),
        ], 'keystone-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'keystone-migrations');

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
```

- [ ] **Step 3: Replace `app/Models/User.php` in full**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use BSPDX\Keystone\Traits\HasKeystone;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasKeystone;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

- [ ] **Step 4: Replace `bootstrap/providers.php` in full**

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    BSPDX\Keystone\KeystoneServiceProvider::class,
];
```

- [ ] **Step 5: Delete the auth-only `src/` files**

```bash
rm src/Models/KeystoneUser.php
rm src/Models/Passkey.php
rm src/Contracts/HasPasskeys.php
rm src/Http/Controllers/LoginController.php
rm src/Http/Controllers/PasskeyAuthController.php
rm src/Http/Controllers/TwoFactorAuthController.php
rm src/Http/Controllers/AccountDeletionController.php
rm src/Http/Controllers/ProfileController.php
rm src/Http/Controllers/Concerns/ThrottlesAuthentication.php
rmdir src/Http/Controllers/Concerns 2>/dev/null || true
rm src/Http/Middleware/RequirePasswordConfirm.php
rm src/Http/Middleware/EnsureTwoFactorEnabled.php
rm src/Http/Middleware/RequirePasskey2FA.php
rm src/Services/Contracts/PasskeyServiceInterface.php
rm src/Services/PasskeyService.php
rm src/Support/PasskeyConfig.php
rmdir src/Support 2>/dev/null || true
rm src/Actions/GeneratePasskeyRegisterOptionsAction.php
rmdir src/Actions 2>/dev/null || true
rm src/Console/Commands/MakeUserCommand.php
rm src/Console/Commands/ChangePasswordCommand.php
```

- [ ] **Step 6: Verify no leftover references**

Run: `grep -rn "Fortify\|Sanctum\|Passkey\|TwoFactor\|two_factor\|HasApiTokens" src/`
Expected: no output (empty). If anything prints, it's a reference this task missed — fix it before moving on.

Run: `php -l src/Traits/HasKeystone.php && php -l src/KeystoneServiceProvider.php && php -l app/Models/User.php`
Expected: `No syntax errors detected` for all three.

---

### Task 3: Remove all Blade UI

**Files:**
- Delete: `resources/views/` (entire directory)
- Delete: `src/View/Components/LoginForm.php`
- Delete: `src/View/Components/RegisterForm.php`
- Delete: `src/View/Components/PasskeyLogin.php`
- Delete: `src/View/Components/PasskeyRegister.php`
- Delete: `src/View/Components/TwoFactorChallenge.php`
- Delete: `resources/css/app.css`
- Delete: `resources/js/app.js`
- Delete: `resources/js/bootstrap.js`

**Interfaces:**
- Consumes: nothing.
- Produces: no `resources/views` directory, no `src/View` directory. Later tasks (docs) assume
  there is nothing left to publish under `keystone-views` and no Blade components to document.

- [ ] **Step 1: Delete the views and view components**

```bash
rm -rf resources/views
rm -rf src/View
```

- [ ] **Step 2: Delete the CSS/JS scaffolding**

```bash
rm -rf resources/css resources/js
```

- [ ] **Step 3: Verify nothing references the deleted views/components**

Run: `grep -rn "loadViewsFrom\|loadViewComponentsAs\|resources/views\|keystone::\|keystone-views" src/ routes/ | grep -v vendor`
Expected: no output. (Task 2 already removed the `loadViewsFrom`/`loadViewComponentsAs`/`keystone-views` calls from the service provider — this just confirms nothing was missed.)

---

### Task 4: Migrations — delete auth migrations, fix the `PasskeyConfig` dependency, trim the users-table migration

**Files:**
- Delete: `database/migrations/2024_01_01_00000_create_keystone_users_table.php`
- Delete: `database/migrations/2024_01_01_00004_create_passkeys_table.php`
- Delete: `database/migrations/2024_01_01_00005_add_auth_preferences_to_users_table.php`
- Delete: `tests/database/migrations/2024_01_01_00004_create_passkeys_table.php`
- Delete: `tests/database/migrations/2024_01_01_00005_add_auth_preferences_to_users_table.php`
- Delete: `tests/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`
- Modify: `database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php`
- Modify: `tests/database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php`
- Modify: `database/migrations/2024_01_01_00002_create_permission_tables.php`
- Modify: `tests/database/migrations/2024_01_01_00002_create_permission_tables.php`
- Modify: `database/migrations/2024_01_01_00003_add_tenant_id_to_pivot_tables.php`
- Modify: `tests/database/migrations/2024_01_01_00003_add_tenant_id_to_pivot_tables.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a migration set that creates only `roles`, `permissions`, `model_has_roles`,
  `model_has_permissions`, `role_has_permissions`, and (when multi-tenant) a `tenant_id` column
  on the host's users table and pivot tables. No migration references `KeystoneUser`, `Passkey`,
  or `PasskeyConfig`.

- [ ] **Step 1: Delete the removed-feature migrations**

```bash
rm database/migrations/2024_01_01_00000_create_keystone_users_table.php
rm database/migrations/2024_01_01_00004_create_passkeys_table.php
rm database/migrations/2024_01_01_00005_add_auth_preferences_to_users_table.php
rm tests/database/migrations/2024_01_01_00004_create_passkeys_table.php
rm tests/database/migrations/2024_01_01_00005_add_auth_preferences_to_users_table.php
rm tests/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php
```

- [ ] **Step 2: Replace `database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php` in full**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Resolve table name dynamically
        $authenticatable = config('auth.providers.users.model', \App\Models\User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Multi-tenancy support (only if enabled in features)
            // NOTE: tenant_id is always UUID, regardless of the user model's own primary key type
            if (config('keystone.features.multi_tenant', false) && !Schema::hasColumn($tableName, 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        // Resolve table name dynamically
        $authenticatable = config('auth.providers.users.model', \App\Models\User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
```

- [ ] **Step 3: Replace `tests/database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php` in full**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        $authenticatable = config('auth.providers.users.model', \App\Models\User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        $authenticatable = config('auth.providers.users.model', \App\Models\User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['tenant_id']);
        });
    }
};
```

- [ ] **Step 4: Fix the `PasskeyConfig` dependency in `database/migrations/2024_01_01_00002_create_permission_tables.php`**

Remove the import line:

```php
use BSPDX\Keystone\Support\PasskeyConfig;
```

Replace:

```php
        // Detect if the authenticatable model uses UUIDs by checking for the HasUuids trait
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds') && count($authenticatable->uniqueIds()) > 0;
```

with:

```php
        // Detect if the authenticatable model uses UUIDs by checking for the HasUuids trait
        $authenticatableClass = config('keystone.user.model')
            ?? config('auth.providers.users.model', \App\Models\User::class);
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds') && count($authenticatable->uniqueIds()) > 0;
```

- [ ] **Step 5: Apply the identical fix to `tests/database/migrations/2024_01_01_00002_create_permission_tables.php`**

Same import removal and same replacement as Step 4, applied to the test-mirror copy of the file (its content is otherwise identical).

- [ ] **Step 6: Fix the `PasskeyConfig` dependency in `database/migrations/2024_01_01_00003_add_tenant_id_to_pivot_tables.php`**

Remove the import line:

```php
use BSPDX\Keystone\Support\PasskeyConfig;
```

Replace:

```php
        // Detect if user model uses UUIDs
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds');
        $teamForeignKey = 'tenant_id';
```

with:

```php
        // Detect if user model uses UUIDs
        $authenticatableClass = config('keystone.user.model')
            ?? config('auth.providers.users.model', \App\Models\User::class);
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds');
        $teamForeignKey = 'tenant_id';
```

- [ ] **Step 7: Apply the identical fix to `tests/database/migrations/2024_01_01_00003_add_tenant_id_to_pivot_tables.php`**

Remove the import line:

```php
use BSPDX\Keystone\Support\PasskeyConfig;
```

Replace:

```php
        // Detect if user model uses UUIDs (same logic as permission migration line 24)
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds');
```

with:

```php
        // Detect if user model uses UUIDs (same logic as permission migration line 24)
        $authenticatableClass = config('keystone.user.model')
            ?? config('auth.providers.users.model', \App\Models\User::class);
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds');
```

- [ ] **Step 8: Verify no migration references `PasskeyConfig`, `KeystoneUser`, or `Passkey` anymore**

Run: `grep -rln "PasskeyConfig\|KeystoneUser\|Spatie\\\\LaravelPasskeys" database/migrations tests/database/migrations`
Expected: no output.

Run: `for f in database/migrations/*.php tests/database/migrations/*.php; do php -l "$f" || exit 1; done`
Expected: `No syntax errors detected` for every file.

---

### Task 5: Prune `config/keystone.php`

**Files:**
- Modify: `config/keystone.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a config file with only `load_routes`, `user.model`, `features.multi_tenant`,
  `rbac.*`. Task 7 (tests) and Task 9 (docs) assume every other key documented in the old file
  is gone.

- [ ] **Step 1: Replace `config/keystone.php` in full**

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Routes Automatically
    |--------------------------------------------------------------------------
    |
    | Determines whether Keystone should automatically load its routes.
    | Set to false to manually define routes in your application.
    |
    */

    'load_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    |
    | Specify which User model to use. Keystone does not own a User model —
    | this should point at your own application's authenticatable model,
    | which should use the BSPDX\Keystone\Traits\HasKeystone trait.
    |
    */

    'user' => [
        // User model class to use.
        // Default: null (uses config('auth.providers.users.model'))
        'model' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle Keystone-specific functionality on/off.
    |
    */

    'features' => [
        // Enable multi-tenant mode (adds tenant_id to users table)
        'multi_tenant' => env('KEYSTONE_MULTI_TENANT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Role & Permission Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the role-based access control (RBAC) system.
    |
    */

    'rbac' => [
        // Cache expiration time for roles and permissions (in seconds)
        'cache_expiration' => 60 * 60 * 24, // 24 hours

        // Default role assigned to new users (null = no default role)
        'default_role' => 'user',

        // Super admin role that bypasses all permission checks
        'super_admin_role' => 'super-admin',
    ],

];
```

- [ ] **Step 2: Verify the config file is valid PHP**

Run: `php -l config/keystone.php`
Expected: `No syntax errors detected`

---

### Task 6: Prune `routes/web.php` and `routes/api.php`

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `RolePermissionController` (unchanged, from `src/Http/Controllers/RolePermissionController.php`).
- Produces: `routes/web.php` and `routes/api.php` containing only RBAC-protected route examples.

- [ ] **Step 1: Replace `routes/web.php` in full**

```php
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keystone Web Routes
|--------------------------------------------------------------------------
|
| These are example RBAC-protected routes for the BSPDX Keystone package.
| Copy these routes to your routes/web.php file and customize as needed.
|
| Keystone does not provide authentication. Apply your own auth middleware
| (Fortify, Breeze, or anything else) alongside the 'role'/'permission'
| middleware shown below.
|
*/

// Example RBAC protected routes
Route::middleware(['web', 'auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return 'Admin Dashboard';
    });
});

Route::middleware(['web', 'auth', 'permission:edit-posts'])->group(function () {
    Route::get('/posts/edit', function () {
        return 'Edit Posts';
    });
});
```

- [ ] **Step 2: Replace `routes/api.php` in full**

```php
<?php

use Illuminate\Support\Facades\Route;
use BSPDX\Keystone\Http\Controllers\RolePermissionController;

/*
|--------------------------------------------------------------------------
| Keystone API Routes
|--------------------------------------------------------------------------
|
| These are example API routes for the BSPDX Keystone package.
| Copy these routes to your routes/api.php file and customize as needed.
|
| Keystone does not require or assume Sanctum — it's shown below only as
| the most common choice. Protect this group with whatever auth guard
| your application actually uses.
|
*/

// Role & Permission Management API
Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    // View roles and permissions
    Route::get('/roles', [RolePermissionController::class, 'roles'])
        ->middleware('permission:view-roles')
        ->name('api.roles.index');

    Route::get('/permissions', [RolePermissionController::class, 'permissions'])
        ->middleware('permission:view-permissions')
        ->name('api.permissions.index');

    // Create roles and permissions
    Route::post('/roles', [RolePermissionController::class, 'createRole'])
        ->middleware('permission:create-roles')
        ->name('api.roles.store');

    Route::post('/permissions', [RolePermissionController::class, 'createPermission'])
        ->middleware('permission:create-permissions')
        ->name('api.permissions.store');

    // Delete roles and permissions
    Route::delete('/roles/{role}', [RolePermissionController::class, 'deleteRole'])
        ->middleware('permission:delete-roles')
        ->name('api.roles.destroy');

    Route::delete('/permissions/{permission}', [RolePermissionController::class, 'deletePermission'])
        ->middleware('permission:delete-permissions')
        ->name('api.permissions.destroy');

    // Assign roles and permissions to users
    Route::post('/users/{user}/roles', [RolePermissionController::class, 'assignRoles'])
        ->middleware('permission:assign-roles')
        ->name('api.users.roles.assign');

    Route::post('/users/{user}/permissions', [RolePermissionController::class, 'assignPermissions'])
        ->middleware('permission:assign-permissions')
        ->name('api.users.permissions.assign');

    // Get user roles and permissions
    Route::get('/users/{user}/roles-permissions', [RolePermissionController::class, 'userRolesPermissions'])
        ->middleware('permission:view-users')
        ->name('api.users.roles-permissions');

    // Assign permissions to roles
    Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'assignPermissionsToRole'])
        ->middleware('permission:assign-permissions')
        ->name('api.roles.permissions.assign');
});
```

- [ ] **Step 3: Verify both route files parse**

Run: `php -l routes/web.php && php -l routes/api.php`
Expected: `No syntax errors detected` for both.

---

### Task 7: Prune the test suite

**Files:**
- Delete: `tests/Feature/AccountDeletionTest.php`
- Delete: `tests/Feature/PasskeyConfigTest.php`
- Delete: `tests/Feature/PasskeyTwoFactorTest.php`
- Delete: `tests/Feature/RateLimitingTest.php`
- Delete: `tests/Feature/RequirePasswordConfirmTest.php`
- Delete: `tests/Feature/TwoFactorConfigTest.php`
- Delete: `tests/Feature/ConfigRegressionTest.php`
- Delete: `tests/Feature/ShowPermissionsTest.php`
- Modify: `tests/Feature/KeystoneTest.php`
- Modify: `tests/Unit/HasKeystoneTraitTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (Task 2), `BSPDX\Keystone\Models\KeystoneRole`/`KeystonePermission` (unchanged).
- Produces: a test suite with no references to deleted auth code. Task 8 runs this suite.

- [ ] **Step 1: Delete the auth-only test files**

```bash
rm tests/Feature/AccountDeletionTest.php
rm tests/Feature/PasskeyConfigTest.php
rm tests/Feature/PasskeyTwoFactorTest.php
rm tests/Feature/RateLimitingTest.php
rm tests/Feature/RequirePasswordConfirmTest.php
rm tests/Feature/TwoFactorConfigTest.php
rm tests/Feature/ConfigRegressionTest.php
rm tests/Feature/ShowPermissionsTest.php
```

- [ ] **Step 2: Replace `tests/Feature/KeystoneTest.php` in full**

```php
<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;

class KeystoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache using Keystone's cache service
        app(\BSPDX\Keystone\Services\Contracts\CacheServiceInterface::class)->clearPermissionCache();

        // Register test routes for middleware testing
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:admin'])
            ->get('/test-admin-route', function () {
                return response()->json(['message' => 'Success']);
            });
    }

    #[Test]
    public function user_can_be_assigned_a_role()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'admin']);

        $user->assignRole('admin');

        $this->assertTrue($user->hasRole('admin'));
    }

    #[Test]
    public function user_can_be_assigned_a_permission()
    {
        $user = User::factory()->create();
        $permission = KeystonePermission::create(['name' => 'edit-posts']);

        $user->givePermissionTo('edit-posts');

        $this->assertTrue($user->can('edit-posts'));
    }

    #[Test]
    public function role_can_have_permissions()
    {
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'publish-posts']);

        $role->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo($permission));
    }

    #[Test]
    public function user_inherits_permissions_from_role()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'publish-posts']);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('publish-posts'));
    }

    #[Test]
    public function super_admin_can_be_identified()
    {
        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->isSuperAdmin());
    }

    #[Test]
    public function middleware_blocks_users_without_required_role()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-admin-route')
            ->assertStatus(403);
    }

    #[Test]
    public function middleware_allows_users_with_required_role()
    {
        $user = User::factory()->create();
        $adminRole = KeystoneRole::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->actingAs($user)
            ->get('/test-admin-route')
            ->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }

    #[Test]
    public function middleware_blocks_users_without_required_permission()
    {
        $user = User::factory()->create();
        $permission = KeystonePermission::create(['name' => 'edit-posts']);

        $this->assertFalse($user->can('edit-posts'));
    }

    #[Test]
    public function super_admin_bypasses_permission_checks()
    {
        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);
        $user->assignRole($superAdminRole);

        // Super admin should bypass permission checks
        $this->assertTrue($user->canBypassPermissions());
    }
}
```

- [ ] **Step 3: Replace `tests/Unit/HasKeystoneTraitTest.php` in full**

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;

class HasKeystoneTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache using Keystone's cache service
        app(\BSPDX\Keystone\Services\Contracts\CacheServiceInterface::class)->clearPermissionCache();
    }

    #[Test]
    public function it_can_identify_super_admin()
    {
        config(['keystone.rbac.super_admin_role' => 'super-admin']);

        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);

        $this->assertFalse($user->isSuperAdmin());

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->isSuperAdmin());
    }

    #[Test]
    public function it_can_check_if_user_can_bypass_permissions()
    {
        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);

        $this->assertFalse($user->canBypassPermissions());

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->canBypassPermissions());
    }
}
```

- [ ] **Step 4: Verify no test references deleted classes**

Run: `grep -rln "Fortify\|Sanctum\|Passkey\|TwoFactor\|two_factor\|KeystoneUser\|ProfileController\|LoginController\|AccountDeletionController" tests/`
Expected: no output.

Run: `for f in tests/Feature/*.php tests/Unit/*.php tests/Unit/*/*.php; do php -l "$f" || exit 1; done`
Expected: `No syntax errors detected` for every file.

---

### Task 8: Run the full suite, fix any fallout, run Pint

**Files:**
- Modify: any file the test run or linter flags (expected to be none beyond what Tasks 1-7 already changed; if something is flagged, fix it in place).

**Interfaces:**
- Consumes: everything produced by Tasks 1-7.
- Produces: a green `composer test` run and clean `composer lint` run. Task 9 (docs) reports the
  exact passing test/assertion counts from this run.

- [ ] **Step 1: Clear cached config and run the test suite**

Run: `composer test`
Expected: all tests pass. If a test fails, read the failure — it almost always means a leftover
reference to something deleted in Tasks 1-7 (e.g. a stray `two_factor_secret` column
expectation, a missing `tenant_id` default). Fix the specific file causing the failure and
re-run. Do not weaken assertions to make them pass; fix the actual gap.

- [ ] **Step 2: Record the exact test/assertion counts**

Note the final line of the `composer test` output (e.g. `Tests: 47 passed (132 assertions)`) —
Task 9 needs these exact numbers for the CHANGELOG.

- [ ] **Step 3: Run Pint**

Run: `composer lint`
Expected: no style violations reported (Pint auto-fixes what it can; if it reports changes made,
re-run `composer lint` once more to confirm a clean pass).

- [ ] **Step 4: Confirm autoloading is intact**

Run: `composer dump-autoload -o`
Expected: completes with no "class not found"-style warnings.

---

### Task 9: Rewrite README.md and docs/USER_MODEL.md, delete moot docs

**Files:**
- Modify: `README.md` (full rewrite)
- Modify: `docs/USER_MODEL.md` (full rewrite)
- Delete: `docs/https-setup.md`
- Delete: `PASSKEY-AUTHENTICATABLE-CONFIG-BUG.md`

**Interfaces:**
- Consumes: the exact test/assertion counts recorded in Task 8, Step 2.
- Produces: docs describing only the RBAC/multi-tenancy feature set.

- [ ] **Step 1: Delete the two moot docs**

```bash
rm docs/https-setup.md
rm -f PASSKEY-AUTHENTICATABLE-CONFIG-BUG.md
```

(`PASSKEY-AUTHENTICATABLE-CONFIG-BUG.md` is untracked — `rm -f` succeeds whether or not it's
still present.)

- [ ] **Step 2: Replace `README.md` in full**

```markdown
# BSPDX Keystone

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bspdx/keystone.svg?style=flat-square)](https://packagist.org/packages/bspdx/keystone)
[![Total Downloads](https://img.shields.io/packagist/dt/bspdx/keystone.svg?style=flat-square)](https://packagist.org/packages/bspdx/keystone)
[![License](https://img.shields.io/packagist/l/bspdx/keystone.svg?style=flat-square)](https://packagist.org/packages/bspdx/keystone)

A multi-tenant role-based access control (RBAC) package for Laravel. Keystone adds roles,
permissions, and tenant-scoped authorization to whatever User model your application already
has — it does not provide authentication. Bring your own login, registration, 2FA, and
passkeys (Fortify, Breeze, or anything else); Keystone plugs in via a single trait.

-   👥 **Role-Based Access Control (RBAC)** - Clean service layer API
-   🏢 **Multi-Tenancy Ready** - Optional, global-scope tenant isolation
-   🚪 **Laravel Gate Integration** - Permissions registered via `Gate::before()`
-   🌐 **API-First Design** - JSON role/permission management API included
-   🔌 **Framework-Agnostic Auth** - Works alongside any authentication stack

## Table of Contents

-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Usage](#usage)
    -   [Adding RBAC to Your User Model](#adding-rbac-to-your-user-model)
    -   [Service Layer](#service-layer)
    -   [Routes](#routes)
    -   [Middleware](#middleware)
    -   [Checking Permissions in Code](#checking-permissions-in-code)
    -   [API Usage](#api-usage)
-   [Architecture](#architecture)
-   [Multi-Tenancy](#multi-tenancy)
-   [Testing](#testing)
-   [Credits](#credits)
-   [License](#license)

## Requirements

-   PHP 8.2+
-   Laravel 12.x or 13.x
-   MySQL 5.7+ / PostgreSQL 9.6+ / SQLite 3.8.8+

## Installation

### Step 1: Install via Composer

```bash
composer require bspdx/keystone
```

### Step 2: Publish Configuration & Migrations

```bash
php artisan vendor:publish --tag=keystone-config --tag=keystone-migrations

# Optional: example RBAC API routes
php artisan vendor:publish --tag=keystone-routes

# Optional: demo roles/permissions/users seeder
php artisan vendor:publish --tag=keystone-seeders
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates tables for roles and permissions (custom RBAC), and — if `KEYSTONE_MULTI_TENANT`
is enabled — a `tenant_id` column on your users table and on the role/permission pivot tables.

### Step 4: Seed Demo Data (Optional)

```bash
php artisan db:seed --class=KeystoneSeeder
```

This creates 4 default roles (`super-admin`, `admin`, `editor`, `user`) with common
permissions, and 4 demo users assigned to them.

### Step 5: Set Up Your Own Authentication

Keystone does not configure or require any authentication package. Install and configure
`laravel/fortify` (or Breeze, or your own solution) exactly as you would in a Keystone-free
Laravel app. Keystone only needs your User model to exist and to use the `HasKeystone` trait
(see below) — it has no opinion on how users log in.

## Configuration

The package configuration is located at `config/keystone.php`.

### Feature Flags

```php
'features' => [
    // Enable multi-tenant mode (adds tenant_id column to users, roles, and permissions tables)
    'multi_tenant' => env('KEYSTONE_MULTI_TENANT', false),
],
```

When `multi_tenant` is enabled, Keystone adds a nullable `tenant_id` column to users, roles,
permissions, and pivot tables, and uses **global scopes** for automatic tenant isolation (not
Spatie's teams feature).

**Key Features:**
- **Automatic Filtering** - Authenticated users only see roles/permissions for their tenant
- **Global Roles/Permissions** - Set `tenant_id = NULL` for cross-tenant access
- **UUID Support** - `tenant_id` is always a UUID column
- **Super-Admin Bypass** - Use `::withoutTenant()` for cross-tenant operations

See [Multi-Tenancy Documentation](docs/multi-tenancy.md) for detailed architecture, usage
examples, and migration guides.

### RBAC Settings

```php
'rbac' => [
    // Cache expiration time for roles and permissions (in seconds)
    'cache_expiration' => 60 * 60 * 24, // 24 hours

    // Default role assigned to new users (null = no default role)
    'default_role' => 'user',

    // Super admin role that bypasses all permission checks
    'super_admin_role' => 'super-admin',
],
```

## Usage

### Adding RBAC to Your User Model

Add the `HasKeystone` trait to your existing `User` model — this is Keystone's only
integration point. It adds role/permission relationships and tenant-aware scoping; it does not
touch authentication.

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use BSPDX\Keystone\Traits\HasKeystone;

class User extends Authenticatable
{
    use Notifiable, HasKeystone;

    // Add whatever your own auth stack requires, e.g.:
    // use Laravel\Fortify\TwoFactorAuthenticatable;
    // use Laravel\Sanctum\HasApiTokens;

    // ... rest of your model
}
```

You can also query users by assigned role directly from the model:

```php
use App\Models\User;

$admins = User::role('admin')->get();
$staff = User::role(['admin', 'manager'])->get();
```

### Service Layer

All role and permission operations go through dedicated services, abstracted behind interfaces.

#### Using Services in Controllers

```php
<?php

namespace App\Http\Controllers;

use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;

class AdminController extends Controller
{
    public function __construct(
        private RoleServiceInterface $roleService,
        private PermissionServiceInterface $permissionService,
        private AuthorizationServiceInterface $authService
    ) {}

    public function assignRole(User $user)
    {
        // Get all roles
        $roles = $this->roleService->getAllWithPermissions();

        // Assign roles to user
        $this->authService->assignRolesToUser($user, ['admin', 'editor']);

        // Check if user has role
        if ($this->authService->userHasRole($user, 'admin')) {
            // User is admin
        }
    }
}
```

**Benefits:**
- Clean dependency injection
- Easy to mock for testing
- No direct external package dependencies in your code

### Routes

Keystone doesn't auto-register routes. Add them manually from the published examples:

**Web Routes** (`routes/keystone-web.php`):

```php
// Include in your routes/web.php
require __DIR__.'/keystone-web.php';
```

**API Routes** (`routes/keystone-api.php`):

```php
// Include in your routes/api.php
require __DIR__.'/keystone-api.php';
```

### Middleware

Keystone registers three middleware aliases:

| Alias | Purpose |
| --- | --- |
| `role:<role>` | Require one of the listed roles (OR logic) |
| `permission:<perm>` | Require one of the listed permissions (OR logic) |
| `keystone.feature:<name>` | Return `404` if the named feature flag is disabled |

#### Role Middleware

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Only users with 'admin' role can access
});

// Multiple roles (OR logic)
Route::middleware(['auth', 'role:admin,editor'])->group(function () {
    // Users with 'admin' OR 'editor' role can access
});
```

#### Permission Middleware

```php
Route::middleware(['auth', 'permission:edit-posts'])->group(function () {
    // Only users with 'edit-posts' permission
});

// Multiple permissions
Route::middleware(['auth', 'permission:edit-posts,publish-posts'])->group(function () {
    // Users with either permission can access
});
```

#### Feature Flag Middleware

Gate routes behind a `keystone.features.*` flag. Requests to a disabled feature return `404`.

```php
Route::middleware(['auth', 'keystone.feature:multi_tenant'])->group(function () {
    // Only reachable when 'multi_tenant' is enabled in config
});
```

### Checking Permissions in Code

#### Traditional Approach (User Model Methods)

```php
// Check role
if (auth()->user()->hasRole('admin')) {
    // User is an admin
}

// Check permission
if (auth()->user()->can('edit-posts')) {
    // User can edit posts
}

// Check multiple roles
if (auth()->user()->hasAnyRole(['admin', 'editor'])) {
    // User has at least one of these roles
}

// Super admin check
if (auth()->user()->isSuperAdmin()) {
    // User is super admin (bypasses all permission checks)
}
```

#### Service Layer Approach (Recommended for Controllers)

```php
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;

class PostController extends Controller
{
    public function __construct(
        private AuthorizationServiceInterface $authService
    ) {}

    public function edit(Post $post)
    {
        if ($this->authService->userHasPermission(auth()->user(), 'edit-posts')) {
            // User can edit posts
        }
    }
}
```

### API Usage

Keystone's role/permission management API is JSON-first, making it easy to drive from any
frontend. Protect the API routes with whatever auth guard your application uses:

```bash
curl -X GET http://localhost/api/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Assign Role to User:**

```bash
curl -X POST http://localhost/api/users/1/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"roles": ["admin"]}'
```

## Architecture

### Service Layer

All role and permission operations go through dedicated services:

- **RoleService** - Role CRUD and queries
  - `getAllWithPermissions()`, `create()`, `delete()`, `syncPermissions()`
- **PermissionService** - Permission CRUD and queries
  - `getAllWithRoles()`, `create()`, `delete()`, `syncToUser()`
- **AuthorizationService** - High-level authorization operations
  - `assignRolesToUser()`, `assignPermissionsToUser()`, `userHasRole()`, `userHasPermission()`

All services are registered in Laravel's service container with interface bindings and
convenient aliases:
- `keystone.roles`
- `keystone.permissions`
- `keystone.authorization`
- `keystone.cache`

### Models

- `BSPDX\Keystone\Models\KeystoneRole` - Custom role model with tenant scoping and `isSuperAdmin()`
- `BSPDX\Keystone\Models\KeystonePermission` - Custom permission model with tenant scoping

### Benefits

- **Testability** - Mock service interfaces in tests instead of facades
- **Maintainability** - RBAC logic isolated in a dedicated service layer
- **Backend-Only** - No UI to maintain or theme; drop it into any frontend

## Multi-Tenancy

Keystone provides comprehensive multi-tenant support using **global scopes** for automatic
tenant isolation. Roles and permissions can be global (accessible across all tenants) or
tenant-specific (isolated per organization).

Keystone handles the **RBAC side of multi-tenancy** — scoping roles, permissions, and
assignments to a `tenant_id`. It does not provide a `Tenant` model, tenant creation, or
user-to-tenant assignment. Your application is responsible for managing tenants and populating
`tenant_id` on your `User` model. Keystone reads that value automatically to scope all role and
permission queries.

### Quick Start

Enable multi-tenancy in your `.env`:

```env
KEYSTONE_MULTI_TENANT=true
```

### Features

- **Automatic Tenant Filtering** - Global scopes automatically filter roles/permissions by authenticated user's tenant
- **Global Roles/Permissions** - Set `tenant_id = NULL` to make roles/permissions accessible across all tenants
- **Tenant-Specific Roles** - Roles with `tenant_id` are isolated to a single organization
- **UUID Support** - `tenant_id` is always a UUID column
- **Super-Admin Bypass** - Use `::withoutTenant()` scope for cross-tenant operations

### Usage Examples

#### Creating Global Roles

```php
use BSPDX\Keystone\Models\KeystoneRole;

// Create a global role accessible to all tenants
$superAdmin = KeystoneRole::withoutTenant()->create([
    'name' => 'super_administrator',
    'title' => 'Super Administrator',
    'tenant_id' => null,  // Global role
]);
```

#### Creating Tenant-Specific Roles

```php
// tenant_id is auto-populated from authenticated user
Auth::login($userInTenantA);

$manager = KeystoneRole::create([
    'name' => 'department_manager',
    'title' => 'Department Manager',
    // tenant_id automatically set from auth()->user()->tenant_id
]);
```

#### Super-Admin Operations

```php
// View all roles across all tenants
$allRoles = KeystoneRole::withoutTenant()->get();

// Check if user can bypass tenant filtering
if ($user->canBypassPermissions()) {
    // User is super-admin
}
```

### Keystone Management Commands

```bash
# Create roles and permissions
php artisan keystone:make-role manager
php artisan keystone:make-permission edit-posts

# Assign and remove roles/permissions
php artisan keystone:assign-role admin --user={user_id}
php artisan keystone:unassign-role admin --user={user_id}
php artisan keystone:assign-permission edit-posts --role=editor
php artisan keystone:unassign-permission edit-posts --role=editor
```

### Learn More

For comprehensive documentation on multi-tenancy:
- [Multi-Tenancy Architecture](docs/multi-tenancy.md) - Global scopes vs Spatie teams
- [Multi-Tenant Usage Examples](docs/examples/multi-tenant-usage.md) - Common patterns and best practices

## Testing

Run the package tests:

```bash
composer test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Security

If you discover any security issues, please email info@bspdx.com instead of using the issue tracker.

## Credits

-   [BSPDX](https://github.com/TheBootstrapParadox)

**Note:** As of v0.10.0, Keystone is a multi-tenant RBAC package only — it does not provide
authentication, and has no runtime dependency on Fortify, Sanctum, or any passkey package.
Bring your own authentication and add the `HasKeystone` trait to your User model.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Support

-   **Documentation:** [Full documentation](https://github.com/TheBootstrapParadox/Keystone/wiki)
-   **Issues:** [GitHub Issues](https://github.com/TheBootstrapParadox/Keystone/issues)
-   **Discussions:** [GitHub Discussions](https://github.com/TheBootstrapParadox/Keystone/discussions)
```

- [ ] **Step 3: Replace `docs/USER_MODEL.md` in full**

```markdown
# User Model Configuration

Keystone does not own a User model. It adds roles, permissions, and tenant scoping to
whatever User model your application already authenticates with (Fortify, Breeze, or anything
else) via a single trait.

## Setup

Add `BSPDX\Keystone\Traits\HasKeystone` to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use BSPDX\Keystone\Traits\HasKeystone;

class User extends Authenticatable
{
    use Notifiable, HasKeystone;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

That's the entire integration. The trait adds:

- `roles()` / `permissions()` relationships (tenant-aware when `keystone.features.multi_tenant` is enabled)
- `assignRole()`, `removeRole()`, `syncRoles()`, `givePermissionTo()`, `revokePermissionTo()`, `syncPermissions()`
- `hasRole()`, `hasAnyRole()`, `hasAllRoles()`, `hasPermissionTo()`, `hasAnyPermission()`, `hasAllPermissions()`, `hasDirectPermission()`
- `isSuperAdmin()`, `canBypassPermissions()`
- A `scopeRole()` query scope: `User::role('admin')->get()`

It does not add anything related to authentication — no password handling, no tokens, no 2FA,
no passkeys. Add those yourself via Fortify, Sanctum, `laravel/passkeys`, or whatever your
application needs; `HasKeystone` composes cleanly alongside any of them.

## Pointing Keystone at a Non-Default Model or Table

By default, Keystone resolves your User model via `config('auth.providers.users.model')`. If
you need to point it at a different class explicitly (for example, a custom guard's model),
set it in `config/keystone.php`:

```php
'user' => [
    'model' => \App\Models\Admin::class,
],
```

Console commands (`keystone:assign-role`, etc.) and the RBAC migrations use this value —
falling back to `auth.providers.users.model` — to resolve which class to query. No table-name
or primary-key-type configuration is needed: Keystone reads your model's own `getTable()` and
detects UUID primary keys via `uniqueIds()` automatically.

## UUID or BigInt Primary Keys

Keystone's role/permission pivot tables (`model_has_roles`, `model_has_permissions`) store
`model_id` as either a UUID or an unsigned big integer, detected automatically from your User
model at migration time (via `uniqueIds()`). No configuration is required — just run
`php artisan migrate` after installing.

`tenant_id` (when multi-tenancy is enabled) is always a UUID column, regardless of your User
model's own primary key type — see [Multi-Tenancy Architecture](multi-tenancy.md).

## Additional Resources

- [Keystone Configuration](../config/keystone.php)
- [HasKeystone Trait](../src/Traits/HasKeystone.php)
- [Laravel Authentication Docs](https://laravel.com/docs/authentication)
```

- [ ] **Step 4: Verify no remaining doc references deleted files**

Run: `grep -rln "KeystoneUser\|keystone-views\|https-setup\|Fortify\|Sanctum\|Passkey\|2FA\|two-factor\|two_factor" README.md docs/USER_MODEL.md`
Expected: no output.

---

### Task 10: `AGENTS.md` — mark the pivot as done

**Files:**
- Modify: `AGENTS.md`

**Interfaces:**
- Consumes: nothing.
- Produces: an `AGENTS.md` that no longer describes this pivot as a future recommendation.

- [ ] **Step 1: Replace the `## Next Steps` through `## Strategic Direction` sections**

Find this block (currently lines 13-30):

```markdown
## Next Steps

1. Evaluate migrating the passkey backend from `spatie/laravel-passkeys` to `laravel/passkeys` (first-party Fortify integration) once it reaches v1.0. **This is the only remaining outstanding item.**

## Strategic Direction

Laravel's first-party `laravel/passkeys` package (released April 2026, pre-1.0) now integrates with Fortify behind `Features::passkeys()`. Combined with Fortify's long-standing TOTP support, Fortify is on a path to owning the full auth ceremony stack — password, TOTP, passkeys, and passwordless login.

**Implication:** Keystone's auth orchestration layer (controllers, service interfaces, passkey/2FA plumbing) will become redundant overlap as `laravel/passkeys` stabilizes. Consumers will have less reason to add Keystone's auth layer on top of something Fortify handles natively with first-party support.

**What remains Keystone's genuine niche:**
- Multi-tenant RBAC — Laravel has no first-party answer for this
- `HasKeystone` trait + `Gate::before()` integration for roles and permissions
- Role-based auth enforcement (`required_for_roles` for 2FA and passkeys, `requires2FA()`, `requiresPasskey()`)

**Recommended long-term pivot:** Keystone becomes a multi-tenant RBAC package with Fortify integration hooks, rather than an auth orchestration package that also includes RBAC. The auth plumbing should eventually defer entirely to Fortify + `laravel/passkeys`, leaving Keystone responsible only for the role/permission/tenancy layer that Laravel has no plans to own.

**Watch:** `spatie/laravel-passkeys` (current dependency) is likely being superseded by `laravel/passkeys`. When the first-party package reaches v1.0, evaluate migrating the passkey backend so Keystone's abstractions sit on top of the official Fortify integration instead of the Spatie bridge.
```

Replace it with:

```markdown
## Strategic Pivot (Completed in v0.10.0)

As of v0.10.0 (2026-08-17), Keystone implements the pivot described below: it is now a
multi-tenant RBAC package only. Authentication orchestration (Fortify integration, Sanctum,
passkeys, TOTP 2FA, password confirmation, account deletion, all Blade UI) has been removed.
Consumers bring their own authentication (Fortify, Breeze, or anything else) and add the
`HasKeystone` trait to their own User model for roles, permissions, and tenant scoping. See
`CHANGELOG.md` for the full breaking-change list and migration guide.

**Original rationale:** Laravel's first-party `laravel/passkeys` package (released April 2026,
pre-1.0) integrates with Fortify behind `Features::passkeys()`. Combined with Fortify's
long-standing TOTP support, Fortify was on a path to owning the full auth ceremony stack —
password, TOTP, passkeys, and passwordless login — making Keystone's own auth orchestration
layer redundant overlap. Multi-tenant RBAC remained Keystone's genuine niche, since Laravel has
no first-party answer for it.
```

- [ ] **Step 2: Add a closing note to `## Historical Notes`**

At the end of the existing `## Historical Notes` bullet list (currently ending with the "Dead
config keys removed" bullet), add one more bullet:

```markdown
- `features.two_factor`, `features.passkeys`, `features.passkey_2fa`, `features.passwordless_login`, `features.account_deletion`, and `features.show_permissions` — along with all their server-side enforcement — were removed entirely in v0.10.0 as part of the Strategic Pivot above. Keystone no longer has any authentication-flavored config.
```

- [ ] **Step 3: Verify the file reads sensibly**

Run: `cat AGENTS.md`
Expected: the file reads top-to-bottom without dangling references to "Next Steps" or a
still-future-tense "Strategic Direction" — the pivot section now describes what already
happened, past tense.

---

### Task 11: CHANGELOG.md entry

**Files:**
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: the exact test/assertion counts recorded in Task 8, Step 2.
- Produces: a `## [0.10.0]` entry at the top of the changelog, above the existing `## [0.9.0]`
  entry, following this repo's Keep-a-Changelog-based format (see `CLAUDE.md`).

- [ ] **Step 1: Insert the new entry**

Immediately after the header block (the `# Changelog` title, the "Note" line, and the `---`
separator, and before the existing `## [0.9.0] - 2026-06-07` line), insert:

```markdown
## [0.10.0] - 2026-08-17

Keystone no longer provides authentication. Fortify integration, Sanctum, passkeys (WebAuthn),
TOTP two-factor authentication, password confirmation, account deletion, and all Blade UI have
been removed. Keystone is now a **multi-tenant RBAC package only** — roles, permissions, and
tenant scoping on top of whatever authentication your application already uses. See the
Migration Guide below.

### Removed

- **BREAKING:** `laravel/fortify`, `laravel/sanctum`, `spatie/laravel-passkeys`, and `pragmarx/google2fa-laravel` dependencies — Keystone no longer requires or configures any authentication package
- **BREAKING:** `BSPDX\Keystone\Models\KeystoneUser` and its migration — Keystone no longer ships an owned User model or users-table migration
- **BREAKING:** `BSPDX\Keystone\Models\Passkey`, `BSPDX\Keystone\Contracts\HasPasskeys`, `PasskeyServiceInterface`, `PasskeyService`, `GeneratePasskeyRegisterOptionsAction`, `PasskeyConfig`
- **BREAKING:** `LoginController`, `PasskeyAuthController`, `TwoFactorAuthController`, `AccountDeletionController`, `ProfileController`, `ThrottlesAuthentication`
- **BREAKING:** `RequirePasswordConfirm`, `EnsureTwoFactorEnabled`, `RequirePasskey2FA` middleware and their `password-confirm`/`2fa`/`passkey-2fa` route aliases
- **BREAKING:** `keystone:make-user` and `keystone:change-password` Artisan commands
- **BREAKING:** All Blade UI — every published view, `src/View/Components/*`, and the `resources/css`/`resources/js` scaffolding
- **BREAKING:** `HasKeystone` trait no longer includes `HasApiTokens` (Sanctum), `TwoFactorAuthenticatable` (Fortify), or `InteractsWithPasskeys` (Spatie Passkeys), and no longer exposes `hasTwoFactorEnabled()`, `requires2FA()`, `hasPasskeysRegistered()`, `requiresPasskey()`, `canUsePasswordlessLogin()`, `getAuthenticationMethods()`, `getAvailableAuthMethods()`, `hasValidAuthConfiguration()`, `getAuthPreferenceFillable()`, `getAuthPreferenceCasts()`, or `twoFactorQrCodeSvg()`
- **BREAKING:** `config('keystone.passkey')`, `config('keystone.two_factor')`, `config('keystone.redirects')`, `config('keystone.rate_limiting')`, `config('keystone.session')`, `config('keystone.profile')`, and the `features.two_factor`/`features.passkeys`/`features.passkey_2fa`/`features.account_deletion`/`features.passwordless_login`/`features.show_permissions` flags
- **BREAKING:** `config('keystone.user.primary_key_type')` and `config('keystone.user.table_name')`

### Changed

- **BREAKING:** `routes/web.php` and `routes/api.php` example files now contain only RBAC-protected route examples
- `add_keystone_fields_to_users_table` migration now only adds `tenant_id` — it no longer adds Fortify 2FA columns
- Package description and keywords now describe Keystone as a multi-tenant RBAC package, not an auth orchestration package

### Migration Guide

1. **Remove `KeystoneUser` usage.** If your `User` model extended `KeystoneUser`, change it to extend `Illuminate\Foundation\Auth\User` (or your own base class) and add `use BSPDX\Keystone\Traits\HasKeystone;` directly — see [`docs/USER_MODEL.md`](docs/USER_MODEL.md).
2. **Set up authentication yourself.** Keystone no longer configures Fortify, Sanctum, or passkeys. Install and configure `laravel/fortify` (and `laravel/sanctum`/`laravel/passkeys` if you need tokens or passkeys) directly, exactly as you would in any Laravel app that doesn't use Keystone.
3. **Remove references to deleted routes/views/commands.** Drop any `keystone-web.php`/`keystone-api.php` includes pointing at the removed login/2FA/passkey/profile/account-deletion routes, delete any published `resources/views/vendor/keystone` customizations, and stop calling `keystone:make-user`/`keystone:change-password`.
4. **Update `config/keystone.php`.** Re-publish with `php artisan vendor:publish --tag=keystone-config --force` and re-apply any custom `rbac.*`/`features.multi_tenant`/`user.model` values — the `passkey`, `two_factor`, `redirects`, `rate_limiting`, `session`, and `profile` sections, and the `features.show_permissions`/`user.primary_key_type`/`user.table_name` keys, are gone.
5. **Run `php artisan migrate`.** The `create_keystone_users_table`, `create_passkeys_table`, and `add_auth_preferences_to_users_table` migrations are removed; `add_keystone_fields_to_users_table` now only adds `tenant_id`, not 2FA columns. If you already ran the old migrations in production, write your own follow-up migration to drop the now-unused `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `allow_passkey_login`, `allow_totp_login`, and `require_password` columns — Keystone no longer manages them but won't silently drop your data.
6. **Reimplement any removed `HasKeystone` auth methods you relied on** (`requires2FA()`, `requiresPasskey()`, etc.) using your own authentication package's APIs — Keystone only tracks roles/permissions now.

### Testing
- ✅ Run `composer test` after completing this plan and record the exact result here, e.g. `All <N> tests passing (<M> assertions)` — use the real numbers from Task 8, Step 2, not placeholders.

---
```

(The closing `---` above matches the separator style already used between every other entry in
this file — keep it.)

- [ ] **Step 2: Fill in the real test count**

Replace the `### Testing` bullet's placeholder text with the actual numbers recorded in Task 8,
Step 2 — e.g. if `composer test` reported `Tests: 41 passed (118 assertions)`, the line should
read:

```markdown
- ✅ All 41 tests passing (118 assertions)
```

- [ ] **Step 3: Verify the changelog is well-formed**

Run: `head -60 CHANGELOG.md`
Expected: `# Changelog` header, the note line, `---`, then the new `## [0.10.0] - 2026-08-17`
entry, then `## [0.9.0] - 2026-06-07` immediately after — no duplicate headers, no broken
Markdown.

---

### Task 12: Final verification and single commit

**Files:** none (verification + commit only).

**Interfaces:**
- Consumes: everything from Tasks 1-11.
- Produces: one commit containing the entire change.

- [ ] **Step 1: Re-run the full verification suite**

Run: `composer test && composer lint`
Expected: tests pass, lint reports no issues (this catches anything the doc/changelog edits in
Tasks 9-11 might have disturbed, e.g. accidental edits to a PHP file while editing prose next to
it — there should be none).

- [ ] **Step 2: Grep for anything left behind**

Run:
```bash
grep -rniE "fortify|sanctum|passkey|two.?factor|2fa" \
  --include="*.php" src/ config/ routes/ database/ tests/ app/ bootstrap/ composer.json \
  | grep -v "HasKeystoneTraitTest.php:.*# " || true
```
Expected: no output. (If anything prints, it's a reference this plan missed — resolve it before
committing.)

- [ ] **Step 3: Review the full diff**

Run: `git status` and `git diff --stat`
Expected: every file listed matches something explicitly created, modified, or deleted by
Tasks 1-11 above — nothing unexpected (e.g. no accidental changes to `database/database.sqlite`
or `.env`).

- [ ] **Step 4: Stage and commit everything as one commit**

```bash
git add -A
git status
```

Confirm the staged file list matches expectations, then:

```bash
git commit -m "$(cat <<'EOF'
feat!: remove authentication, keep multi-tenant RBAC only

Keystone no longer provides authentication (Fortify integration, Sanctum,
passkeys, TOTP 2FA, password confirmation, account deletion, all Blade UI).
It is now a multi-tenant RBAC package only, per the pivot documented in
AGENTS.md — consumers bring their own authentication and add the
HasKeystone trait to their own User model.

See CHANGELOG.md [0.10.0] for the full breaking-change list and migration
guide.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Confirm the commit**

Run: `git log -1 --stat`
Expected: a single new commit on top of the previous `develop` HEAD, containing every file
touched by this plan.
