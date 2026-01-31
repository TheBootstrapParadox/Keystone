# Changelog

All notable changes to `bspdx/keystone` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**Note:** This CHANGELOG was created starting with v0.3.0. Changes prior to this version were not formally documented.

---

## [0.7.2] - Unreleased

### Changed

- The nullable `tenant_id` UUID column is now also added to:
  - `permissions` table for tenant-scoped permissions
  - `roles` table for tenant-scoped roles
- `tenant_id` is nullable: `NULL` means global (accessible across all tenants)
- Unique constraints updated to include `tenant_id` when multi-tenancy is enabled
---

## [0.7.1] - 2026-02-01

### Changed

**Multi-Tenant Configuration Consolidation**
- Consolidated multi-tenancy configuration into `config('keystone.features.multi_tenant')`
- Removed duplicate configurations: `isMultitenant`, `rbac.multi_tenant`, and `multi_tenancy` section
- Environment variable changed from `KEYSTONE_IS_MULTITENANT` to `KEYSTONE_MULTI_TENANT`
- Migration `add_keystone_fields_to_users_table` now checks `features.multi_tenant` config
- When enabled, adds `tenant_id` UUID column to users table for tenant isolation
- Updated `InteractsWithKeystone` trait to use new config location

### Migration Note

If you were using `KEYSTONE_IS_MULTITENANT=true` in your `.env` file, update it to:
```env
KEYSTONE_MULTI_TENANT=true
```

---

## [0.7.0] - 2026-02-01

### Added

**Optional Built-in User Model**
- New `KeystoneUser` model with full authentication support (2FA, passkeys, roles, permissions)
- Configurable primary key type (BigInt or UUID) via `keystone.user.primary_key_type` config
- Configurable table name via `keystone.user.table_name` config
- Optional user table migration that only runs when using `KeystoneUser`
- New `keystone.user.model` config to specify User model class (defaults to host app User)
- Package now provides a complete "one-stop shop" authentication solution for new projects

**Enhanced Roles & Permissions**
- Added `title` column to roles table for human-friendly display names (e.g., "Super Administrator" instead of "super-admin")
- Added `description` column to roles table to document role purpose and scope
- Added `title` column to permissions table for UI display (e.g., "View Users" instead of "view-users")
- Added `description` column to permissions table to explain permission purpose
- Added `getDisplayNameAttribute()` accessor to `KeystoneRole` and `KeystonePermission` models
- Updated `KeystoneSeeder` to populate title and description fields with helpful defaults
- Models now use `$fillable` to allow mass assignment of title/description

**Migration Portability Improvements**
- Migrations now dynamically resolve user table name from auth config (works with custom table names like `id_users`)
- All user table migrations guard against duplicate columns using `Schema::hasColumn()` checks
- Migration column guards applied to: `tenant_id`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `allow_passkey_login`, `allow_totp_login`, `require_password`
- Package now compatible with host apps using custom User model namespaces (e.g., `App\Models\Id\User`)
- Added multi-tenancy support with `tenant_id` column (guarded to prevent duplicates if host app already defines it)

**Documentation**
- New `docs/USER_MODEL.md` guide explaining User model configuration options
- Documentation covers migration from host User to KeystoneUser
- UUID vs BigInt configuration examples
- Custom table name configuration examples

### Changed

- **Console Commands:** All commands now resolve User model from config instead of hardcoding `App\Models\User`
- **InteractsWithKeystone Trait:** Added `getUserModel()` helper method for consistent User resolution
- **KeystoneSeeder:** Now uses config to resolve User model class instead of hardcoded import
- **RolePermissionController:** Updated to use `Illuminate\Contracts\Auth\Authenticatable` interface instead of hardcoded User class
- **Migrations:** `add_authkit_fields_to_users_table` and `add_auth_preferences_to_users_table` now use `config('auth.providers.users.model')` for table resolution
- **Permission Migration:** Updated to include title/description columns in initial schema

### Fixed

- Fixed hardcoded `'users'` table references causing failures with custom table names
- Fixed duplicate column errors when host app already defines 2FA or auth preference columns
- Fixed namespace conflicts when host apps use non-standard User model locations

### Migration Guide

**For Existing Keystone Users (0.6.x → 0.7.0):**

No action required! Version 0.7.0 is fully backward compatible. The package defaults to your existing User model behavior.

**Optional:** To add title/description to existing roles and permissions:

1. Run migrations to add new columns:
```bash
php artisan migrate
```

2. Re-run seeder to populate titles/descriptions:
```bash
php artisan db:seed --class=KeystoneSeeder
```

**For New Projects:**

To use the built-in `KeystoneUser` model:

1. Publish config:
```bash
php artisan vendor:publish --tag=keystone-config
```

2. Edit `config/keystone.php`:
```php
'user' => [
    'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
    'primary_key_type' => 'bigint', // or 'uuid'
    'table_name' => 'users',
],
```

3. Update `config/auth.php`:
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
    ],
],
```

4. Run migrations:
```bash
php artisan migrate
php artisan db:seed --class=KeystoneSeeder
```

**For Custom User Table Names:**

If your app uses a custom user table name (e.g., `id_users`):

```php
// Your User model
protected $table = 'id_users';
```

Keystone 0.7.0+ automatically detects this via `$user->getTable()`. No additional configuration needed!

---

## [0.6.1] - 2026-01-31

#### Added

- New `keystone:change-password` Artisan command to change a user's password by ID or email, with support for random password generation.
- New `keystone:unassign-role` Artisan command to remove role(s) from a user, with `--all` flag to strip all roles.
- New `keystone:unassign-permission` Artisan command to remove permission(s) from a role or user, with `--all` flag support.
- Added `@method` PHPDoc blocks to `KeystoneRole` and `KeystonePermission` models for improved IDE autocompletion of inherited Spatie methods.

## [0.6.0] - 2026-01-25

#### Changed

- Changed all console commands to use `self::SUCCESS` / `self::FAILURE` instead of `Command::SUCCESS` / `Command::FAILURE` to resolve PHP6606 static analysis warnings.

- **BREAKING:** Renamed package from `bspdx/authkit` to `bspdx/keystone` to resolve naming conflict with WorkOS' renowned UI product.
- **BREAKING:** Renamed configuration file from `config/authkit.php` to `config/keystone.php`.
- **BREAKING:** Updated view namespace from `authkit::` to `keystone::`.
- **BREAKING:** Renamed Blade component from `<x-authkit-styles>` to `<x-keystone-styles>`.
- **BREAKING:** Updated CSS class names from `.authkit-*` to `.keystone-*`.
- **BREAKING:** Updated service aliases from `authkit.*` to `keystone.*`.
- **BREAKING:** Updated route names from `authkit.*` to `keystone.*`.
- **BREAKING:** Renamed publish tags from `authkit-*` to `keystone-*`.
- **BREAKING:** Renamed test files from `AuthKitTest.php` to `KeystoneTest.php` and `HasAuthKitTraitTest.php` to `HasKeystoneTraitTest.php`.
- **BREAKING:** Renamed database seeder from `AuthKitSeeder` to `KeystoneSeeder`.
- **BREAKING:** Renamed trait file from `HasAuthKit.php` to `HasKeystone.php` (class already named `HasKeystone`).
- **BREAKING:** Renamed model files from `AuthKitRole.php` to `KeystoneRole.php` and `AuthKitPermission.php` to `KeystonePermission.php` (classes already named accordingly).
- **BREAKING:** Renamed service provider file from `AuthKitServiceProvider.php` to `KeystoneServiceProvider.php` (class already named `KeystoneServiceProvider`).
- **BREAKING:** Renamed command concern trait file from `InteractsWithAuthKit.php` to `InteractsWithKeystone.php` (trait already named `InteractsWithKeystone`).

#### Migration Steps

**1. Update Composer Dependencies**

In your `composer.json`, replace:
```json
"bspdx/authkit": "^0.5"
```

With:
```json
"bspdx/keystone": "^0.6"
```

Then run:
```bash
composer remove bspdx/authkit
composer require bspdx/keystone
```

**2. Update Configuration File**

Rename the configuration file:
```bash
mv config/authkit.php config/keystone.php
```

**3. Update Namespaces**

Replace all namespace references throughout your application:

- `BSPDX\AuthKit\` → `BSPDX\Keystone\`

Examples:
```php
// Before
use BSPDX\AuthKit\Traits\HasAuthKit;
use BSPDX\AuthKit\Models\AuthKitRole;
use BSPDX\AuthKit\Models\AuthKitPermission;
use BSPDX\AuthKit\Services\Contracts\PasskeyServiceInterface;

// After
use BSPDX\Keystone\Traits\HasKeystone;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
```

**4. Update Trait Usage**

In your User model (typically `app/Models/User.php`):
```php
// Before
use BSPDX\AuthKit\Traits\HasAuthKit;
class User extends Authenticatable implements HasPasskeys
{
    use HasAuthKit;
}

// After
use BSPDX\Keystone\Traits\HasKeystone;
class User extends Authenticatable implements HasPasskeys
{
    use HasKeystone;
}
```

**5. Update Service Provider References**

If you've manually registered the service provider in `config/app.php` or `bootstrap/providers.php`:
```php
// Before
BSPDX\AuthKit\AuthKitServiceProvider::class,

// After
BSPDX\Keystone\KeystoneServiceProvider::class,
```

**6. Update Model References in Permission Config**

If you've published the permission configuration (`config/permission.php`):
```php
// Before
'models' => [
    'permission' => \BSPDX\AuthKit\Models\AuthKitPermission::class,
    'role' => \BSPDX\AuthKit\Models\AuthKitRole::class,
],

// After
'models' => [
    'permission' => \BSPDX\Keystone\Models\KeystonePermission::class,
    'role' => \BSPDX\Keystone\Models\KeystoneRole::class,
],
```

**7. Update View Namespaces**

In your Blade views, update view includes:
```blade
{{-- Before --}}
@include('authkit::components.keystone-styles')
@include('authkit::components.login-form')

{{-- After --}}
@include('keystone::components.keystone-styles')
@include('keystone::components.login-form')
```

**8. Update CSS Classes**

If you have custom CSS or HTML targeting component classes:
```css
/* Before */
.authkit-form { }
.authkit-button { }
.authkit-input { }
--authkit-primary

/* After */
.keystone-form { }
.keystone-button { }
.keystone-input { }
--keystone-primary
```

**9. Update Service Aliases**

If you're resolving services from the container using aliases:
```php
// Before
app('authkit.passkey')
app('authkit.roles')
app('authkit.permissions')
app('authkit.authorization')

// After
app('keystone.passkey')
app('keystone.roles')
app('keystone.permissions')
app('keystone.authorization')
```

**10. Update Publish Tags**

If you've published assets using tags:
```bash
# Before
php artisan vendor:publish --tag=authkit-config
php artisan vendor:publish --tag=authkit-views
php artisan vendor:publish --tag=authkit-migrations

# After
php artisan vendor:publish --tag=keystone-config
php artisan vendor:publish --tag=keystone-views
php artisan vendor:publish --tag=keystone-migrations
```

**11. Update Database Seeders**

If you reference the seeder class:
```php
// Before
use Database\Seeders\AuthKitSeeder;

// After
use Database\Seeders\KeystoneSeeder;
```

**12. Update Route References**

If you reference routes by name:
```php
// Before
route('authkit.profile.show')
route('authkit.login.totp')

// After
route('keystone.profile.show')
route('keystone.login.totp')
```

**13. Clear Caches**

After making all changes, clear your application caches:
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
composer dump-autoload
```

**14. Run Tests**

Verify that your application still works correctly:
```bash
php artisan test
```

**Note:** No database migrations are required. The package uses the same table structures and column names.

---

## [0.5.4] 2026-01-24

#### Added

- Added an important notice at the very top of the project's `README.md` informing users about a naming conflict: "I just found out someone else made an AuthKit. I'll get around to renaming this soon, don't you worry!" This ensures visitors see the update immediately and reduces confusion while a rename is planned.

---

## [0.5.3] 2026-01-21

#### Changed

- Hardened passkey flows: clone/transform WebAuthn options client-side, return the exact options JSON with credentials, and validate against stored options during registration/authentication (passkey Blade components, passkey controller/service contract and implementation).

---

## [0.5.2] 2026-01-21

#### Added

- Added `pragmarx/google2fa-laravel` to the package requirements so TOTP flows ship with the core install.

#### Changed

- Updated `resources/views/components/two-factor-challenge.blade.php` to use the shared `authkit-styles` partial for consistent theming and layout.
- AuthKit now registers the Fortify two-factor challenge view automatically when Fortify is present and no override is bound (see `AuthKitServiceProvider`).

---

## [0.5.1] 2026-01-21

#### Removed

- Removed `App\\` namespace from `autoload-dev` in composer.json (was used for local development only)

---

## [0.5.0] 2026-01-21

#### Added

**Passwordless Authentication**
- New `LoginController` to handle passwordless login methods and TOTP authentication
- New passwordless login routes and endpoints
- Support for multiple authentication methods per user

**User Profile Management**
- New `ProfileController` for user profile display and authentication preference updates
- New profile routes and endpoints
- New methods in `HasAuthKit` trait for managing authentication preferences and available methods

**Console Commands**
- New Artisan commands for role management
- New Artisan commands for permission management

**Component Samples**
- Added component samples for common authentication UI patterns

#### Changed

- Enhanced splash page with improved styling and content
- Updated `TwoFactorAuthController` to maintain backward compatibility for recovery codes

---

## [Unreleased] -  0.4.0

#### Changed

**Breaking Changes**
- **BREAKING:** `tenant_id` column is now always a UUID (was `unsignedBigInteger`)
- **BREAKING:** Permission table migrations now auto-detect User model ID type
  - If User model uses `HasUuids` trait, `model_morph_key` columns use `uuid`
  - Otherwise falls back to `unsignedBigInteger`
  - Detection uses `PasskeyConfig::getAuthenticatableModel()` to find the User model

#### Removed

- Removed `0001_01_01_00000_create_users_table.php` migration (conflicts with existing Laravel apps)
- Removed `0001_01_01_00001_create_cache_table.php` migration (host app responsibility)
- Removed `0001_01_01_00002_create_jobs_table.php` migration (host app responsibility)

#### Migration instructions

Review the [Migration Guide](MIGRATING-TO-SUTHKIT-0.4.0md) for help migrating to this new version. 

---

## [0.5.4] 2026-01-24

#### Added

- Added an important notice at the very top of the project's `README.md` informing users about a naming conflict: "I just found out someone else made an AuthKit. I'll get around to renaming this soon, don't you worry!" This ensures visitors see the update immediately and reduces confusion while a rename is planned.

---

## [0.5.3] 2026-01-21

#### Changed

- Hardened passkey flows: clone/transform WebAuthn options client-side, return the exact options JSON with credentials, and validate against stored options during registration/authentication (passkey Blade components, passkey controller/service contract and implementation).

---
  - Provides abstraction layer for passkey authentication contracts

#### Usage

```php
use BSPDX\AuthKit\Contracts\HasPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    use HasAuthKit;
    // ...
}
```

---

## [0.3.0] - 2026-01-19

#### Added

**Service Layer Architecture**
- New `PasskeyService` with interface for all passkey operations
- New `RoleService` with interface for role management
- New `PermissionService` with interface for permission management
- New `AuthorizationService` with interface for high-level authorization operations
- All services registered in Laravel container with interface bindings
- Service aliases: `authkit.passkey`, `authkit.roles`, `authkit.permissions`, `authkit.authorization`

**Model Proxies**
- New `BSPDX\AuthKit\Models\AuthKitRole` - Extends Spatie's Role model
- New `BSPDX\AuthKit\Models\AuthKitPermission` - Extends Spatie's Permission model
- New `AuthKitRole::isSuperAdmin()` method for checking super admin status

#### Changed

**Breaking Changes**
- **BREAKING:** Controllers now use dependency injection for services instead of direct Spatie imports
  - `PasskeyAuthController` now requires `PasskeyServiceInterface` injection
  - `RolePermissionController` now requires `RoleServiceInterface`, `PermissionServiceInterface`, and `AuthorizationServiceInterface` injection
- **BREAKING:** `config/permission.php` now references AuthKit models instead of Spatie models
  - `'models.role'` → `\BSPDX\AuthKit\Models\AuthKitRole::class`
  - `'models.permission'` → `\BSPDX\AuthKit\Models\AuthKitPermission::class`
- **BREAKING:** All public APIs now type-hint AuthKit models (`AuthKitRole`, `AuthKitPermission`) instead of Spatie models
- **BREAKING:** Route model binding for roles and permissions now uses AuthKit models

**Non-Breaking Changes**
- **Improved:** Complete isolation of Spatie dependencies behind service layer
- **Improved:** Controllers no longer contain direct `use Spatie\*` imports
- **Improved:** All external package usage confined to service implementations
- **Maintained:** `HasAuthKit` trait still uses Spatie trait composition (no performance impact)
- **Maintained:** All existing user-facing methods (`hasRole()`, `hasPermission()`, etc.) work unchanged

#### Usage for v0.3.0

Since AuthKit is in beta, refer to the updated README for current usage patterns.

**Using Services in Controllers:**
```php
use BSPDX\AuthKit\Services\Contracts\RoleServiceInterface;

class AdminController extends Controller
{
    public function __construct(
        private RoleServiceInterface $roleService
    ) {}

    public function index()
    {
        $roles = $this->roleService->getAllWithPermissions();
        // ...
    }
}
```

**Using AuthKit Models:**
```php
use BSPDX\AuthKit\Models\AuthKitRole;

$adminRole = AuthKitRole::where('name', 'admin')->first();
if ($adminRole->isSuperAdmin()) {
    // ...
}
```

**Configuration:**
The package's `config/permission.php` automatically uses AuthKit models. No manual changes needed unless you've published the config.

---

## [0.2.0] and earlier

Changes prior to v0.3.0 were not documented in this CHANGELOG.

For historical changes, please see the [Git commit history](https://github.com/TheBootstrapParadox/AuthKit/commits/main).
