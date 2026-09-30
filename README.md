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
-   🌐 **API-First Design** - JSON role/permission management API included, with no-escalation rules built in
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

# Optional: baseline roles/permissions seeder, plus a demo-user seeder
php artisan vendor:publish --tag=keystone-seeders
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates tables for roles and permissions (custom RBAC), and — if `KEYSTONE_MULTI_TENANT`
is enabled — a `tenant_id` column on your users table, the roles and permissions tables, and the
role/permission pivot tables.

Decide on multi-tenancy before you first migrate. To enable it later on an existing install, run
`php artisan keystone:enable-multi-tenancy` (see [Multi-Tenancy](#multi-tenancy)).

### Step 4: Seed Demo Data (Optional)

```bash
php artisan db:seed --class=KeystoneSeeder
```

This creates 4 default roles (`super-admin`, `admin`, `editor`, `user`) with common
permissions. For local development you can also create one demo user per role (password
`password`). That seeder refuses to run in production:

```bash
php artisan db:seed --class=KeystoneDemoUserSeeder
```

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
- **Cross-Tenant Queries** - Use `::withoutTenant()` for cross-tenant operations

See [Multi-Tenancy Documentation](docs/multi-tenancy.md) for detailed architecture, usage
examples, and migration guides.

### RBAC Settings

```php
'rbac' => [
    // Cache expiration time for roles and permissions (in seconds)
    'cache_expiration' => 60 * 60 * 24, // 24 hours

    // Super admin role that bypasses all permission checks
    // (in multi-tenant mode it must be a global role; the name is reserved in tenants)
    'super_admin_role' => 'super-admin',
],
```

### Guards

Whenever you don't pass a guard, Keystone uses your application's default guard
(`auth.defaults.guard`) — when creating roles and permissions, checking them, and resolving
names. Pass a guard explicitly (`$user->hasPermissionTo('edit-posts', 'api')`) to use another.

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

        // Add roles to user (keeps existing roles)
        $this->authService->assignRolesToUser($user, ['admin', 'editor']);

        // Or replace the user's roles with exactly this set
        $this->authService->syncRolesForUser($user, ['editor']);

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

Alternatively, set `keystone.load_routes` to `true` to have Keystone load the management API
routes itself (unless you've published `routes/keystone-api.php`). The web routes are examples
only and are never loaded automatically.

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

Keystone registers these middleware aliases:

| Alias | Purpose |
| --- | --- |
| `keystone.role:<role>` | Require one of the listed roles (OR logic) |
| `keystone.permission:<perm>` | Require one of the listed permissions (OR logic) |
| `role:<role>` | Same as `keystone.role` — registered only if your app hasn't defined a `role` alias |
| `permission:<perm>` | Same as `keystone.permission` — registered only if your app hasn't defined a `permission` alias |

Super-admins pass both. Unauthenticated requests raise Laravel's `AuthenticationException`, so
your app's own guest handling applies (401 for JSON, redirect for browsers).

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

> **Super-admins and literal checks:** super-admins pass every *permission* check
> (`hasPermissionTo()`, `can()`, the `permission:` middleware) and the `role:` middleware, but
> `hasRole()` / `hasAnyRole()` / `hasAllRoles()` and `hasDirectPermission()` are literal. A
> super-admin only "has" the roles and direct permissions actually assigned to them. Use
> `isSuperAdmin()` or a permission check when you mean "may do anything".

> **Gate integration:** Keystone adds one `Gate::before` callback, in every context (HTTP, queue
> workers, the scheduler, tinker). It returns `true` for super-admins and for permissions the
> user holds, and otherwise returns `null` — so your policies and `Gate::define` abilities still
> decide. Keystone never denies on the Gate's behalf.

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

**Add a Role to a User** (keeps the user's other roles):

```bash
curl -X POST http://localhost/api/users/1/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"roles": ["admin"]}'
```

**Replace a User's Roles** (`PUT`; send `[]` to remove all):

```bash
curl -X PUT http://localhost/api/users/1/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"roles": ["editor"]}'
```

The same `POST` adds / `PUT` replaces pattern applies to `/api/users/{user}/permissions` and
`/api/roles/{role}/permissions`.

#### API Security Rules

- **No privilege escalation.** A caller who isn't a super-admin can only grant a role if they hold
  every permission it grants, and only grant (or add to a role) permissions they hold. Only a
  super-admin can grant, remove, or modify the super-admin role. Violations return `403` and
  change nothing. The trait, services, and console commands are not restricted.
- **Global rows are read-only for tenants.** In multi-tenant mode, a caller who belongs to a tenant
  gets `403` when deleting or changing the permissions of a global role or permission.
- **Tenant-aware names.** Names must be unique within the caller's tenant (and guard), and names
  sent to assignment endpoints must exist in the target's tenant (otherwise `422`).

## Architecture

### Service Layer

All role and permission operations go through dedicated services. A `null` guard always means
your default guard.

- **RoleService** - Role CRUD and queries
  - `getAllWithPermissions()`, `create($name, ?$guard)`, `delete($role)`, `syncPermissions($role, $permissions)`, `getUserRoles($user)`, `findByName($name, ?$guard, ?$tenantId)`, `getAllForTenant(?$tenantId)`, `assignToUser()`, `removeFromUser()`, `clearCache()`
- **PermissionService** - Permission CRUD and queries
  - `getAllWithRoles()`, `create($name, ?$guard)`, `delete($permission)`, `syncToUser()`, `getUserPermissions()`, `getAllUserPermissions()`, `findByName()`, `getAllForTenant()`, `assignToUser()`, `removeFromUser()`, `assignToRole()`, `removeFromRole()`
- **AuthorizationService** - High-level authorization operations
  - `assignRolesToUser()`, `assignPermissionsToUser()` (add), `syncRolesForUser()`, `syncPermissionsForUser()` (replace), `userHasRole()`, `userHasAnyRole()`, `userHasAllRoles()`, `userHasPermission()`, `userHasAnyPermission()`, `userHasAllPermissions()`, `userHasDirectPermission()`, `userCanBypassPermissions()` — all with the super-admin bypass applied

All services are registered in Laravel's service container with interface bindings and
convenient aliases:
- `keystone.roles`
- `keystone.permissions`
- `keystone.authorization`
- `keystone.cache`

### Models

- `BSPDX\Keystone\Models\KeystoneRole` - Custom role model with tenant scoping and `isSuperAdmin()`
- `BSPDX\Keystone\Models\KeystonePermission` - Custom permission model with tenant scoping

Assignment methods accept names, integer IDs, models, or arrays of these. Deleting a user
removes their assignments (soft-deleted users keep them until force-deleted).

### Exceptions

All extend `BSPDX\Keystone\Exceptions\KeystoneException`:

- `CannotDeleteSuperAdminRole` - deleting the super-admin role
- `ReservedRoleName` - using the super-admin name for a tenant role (multi-tenant)
- `RoleAlreadyExists` / `PermissionAlreadyExists` - a duplicate name, guard, and tenant (multi-tenant)

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

Enable multi-tenancy in your `.env` before running Keystone's migrations:

```env
KEYSTONE_MULTI_TENANT=true
```

Already migrated in single-tenant mode? Enable it, then upgrade the schema (existing roles and
permissions become global):

```bash
php artisan keystone:enable-multi-tenancy
```

### Features

- **Automatic Tenant Filtering** - Global scopes automatically filter roles/permissions by authenticated user's tenant
- **Global Roles/Permissions** - Set `tenant_id = NULL` to make roles/permissions accessible across all tenants
- **Tenant-Specific Roles** - Roles with `tenant_id` are isolated to a single organization
- **UUID Support** - `tenant_id` is always a UUID column
- **Cross-Tenant Queries** - Use `::withoutTenant()` scope for cross-tenant operations
- **Global Super-Admin** - Only a global role can be the super-admin role; its name is reserved in tenants

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

#### Tenant Query Scopes

Both `KeystoneRole` and `KeystonePermission` provide the same scopes. The automatic tenant
scope always applies, so a tenant user already sees their tenant's rows plus global rows. These
scopes narrow that further. They never widen it.

```php
KeystoneRole::global()->get();                            // global only (tenant_id = NULL)
KeystoneRole::tenantSpecific()->get();                    // tenant-owned only (tenant_id NOT NULL)
KeystoneRole::forTenant($tenantId)->get();                // that tenant's rows only — no globals
KeystoneRole::withoutTenant()->forTenant($otherId)->get(); // explicit cross-tenant read
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
# Create roles and permissions (--tenant creates them in a tenant; global when omitted)
php artisan keystone:make-role manager --permissions=view-posts,edit-posts
php artisan keystone:make-permission edit-posts
php artisan keystone:make-role manager --tenant=019c17d6-1a87-71be-a6a4-718da52579e9

# Assign and remove roles (user by ID or email)
php artisan keystone:assign-role jane@example.com admin
php artisan keystone:assign-role 42 editor --sync
php artisan keystone:unassign-role 42 admin

# Assign and remove permissions (--tenant picks which tenant's role to target)
php artisan keystone:assign-permission edit-posts --to-role=editor
php artisan keystone:assign-permission edit-posts --to-user=42
php artisan keystone:unassign-permission edit-posts --from-role=editor --tenant=019c17d6-1a87-71be-a6a4-718da52579e9

# Upgrade a single-tenant schema after enabling multi-tenancy
php artisan keystone:enable-multi-tenancy
```

### Learn More

For comprehensive documentation on multi-tenancy:
- [Multi-Tenancy Architecture](docs/multi-tenancy.md) - Global scopes vs Spatie teams
- [Multi-Tenant Usage Examples](docs/examples/multi-tenant-usage.md) - Common patterns and best practices

## Testing

Run the package tests:

```bash
composer test                # multi-tenant suite
composer test-single-tenant  # single-tenant suite
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Security

See [API Security Rules](#api-security-rules). If you discover any security issues, please email info@bspdx.com instead of using the issue tracker.

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
