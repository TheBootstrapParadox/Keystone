# Upgrading

## Upgrading to 1.0.0-rc.1

This release closes several security holes in Keystone's authorization and freezes its public API and schema for 1.0. Fixing them changed behavior you may rely on. Start with sections 1–3, which apply to every app, then work through each other section that applies to yours. [CHANGELOG.md](CHANGELOG.md) has the full list of changes.

- [1. Add `implements KeystoneUser` to your user model](#1-add-implements-keystoneuser-to-your-user-model)
- [2. Laravel 12.41 or later](#2-laravel-1241-or-later)
- [3. Update the package and migrations](#3-update-the-package-and-migrations)
- [4. Management API: no privilege escalation](#4-management-api-no-privilege-escalation)
- [5. Management API: tenant callers can't modify global rows](#5-management-api-tenant-callers-cant-modify-global-rows)
- [6. Super-admin must be a global role](#6-super-admin-must-be-a-global-role)
- [7. Gate callback defers to your policies](#7-gate-callback-defers-to-your-policies)
- [8. Gate callback runs in queues and commands](#8-gate-callback-runs-in-queues-and-commands)
- [9. Default guard is `auth.defaults.guard`](#9-default-guard-is-authdefaultsguard)
- [10. `hasDirectPermission()` is literal for super-admins](#10-hasdirectpermission-is-literal-for-super-admins)
- [11. Demo users moved to `KeystoneDemoUserSeeder`](#11-demo-users-moved-to-keystonedemouserseeder)
- [12. `load_routes` loads the API routes only](#12-load_routes-loads-the-api-routes-only)
- [13. `keystone.feature` middleware removed; new namespaced aliases](#13-keystonefeature-middleware-removed-new-namespaced-aliases)
- [14. Custom service implementations](#14-custom-service-implementations)
- [15. Management API response shapes](#15-management-api-response-shapes)
- [16. Management API controllers split by resource](#16-management-api-controllers-split-by-resource)
- [17. Keystone exceptions render as client errors](#17-keystone-exceptions-render-as-client-errors)
- [18. Typed method signatures](#18-typed-method-signatures)
- [19. Switching multi-tenancy on or off](#19-switching-multi-tenancy-on-or-off)

### 1. Add `implements KeystoneUser` to your user model

**Affects:** every app.

Keystone now has a `BSPDX\Keystone\Contracts\KeystoneUser` interface that your user model must declare next to `use HasKeystone;`. The trait already implements every method, so this is one line:

```php
use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Traits\HasKeystone;

class User extends Authenticatable implements KeystoneUser
{
    use HasKeystone;
}
```

Do the same for any other model that holds roles (for example an `Admin` model on another guard). If you forget, the management API and the console commands throw a `LogicException` naming your model class and `KeystoneUser`.

Also changed with it:

- The services take a `KeystoneUser` where they took an `Authenticatable`. Passing another object is a `TypeError`.
- A logged-in user whose model doesn't implement `KeystoneUser` gets **403** from the `role` / `permission` middleware, and the Gate leaves them to your policies.
- Keystone no longer falls back to `App\Models\User`. If neither `keystone.user.model` nor `auth.providers.users.model` is set, it throws a `LogicException` naming both keys.

### 2. Laravel 12.41 or later

**Affects:** apps on Laravel 12.0–12.40.

Keystone now requires `laravel/framework` `^12.41|^13.0`. It relies on `Cache::memo()` (added in 12.9), so the Gate callback reads the permission list from the cache store at most once per request or job, and on `Connection::afterRollBack()` (added in 12.32), so a permission list cached inside a rolled-back transaction is cleared. Update Laravel within 12.x first (`composer update laravel/framework`); that update has no breaking changes of its own.

### 3. Update the package and migrations

```bash
composer update bspdx/keystone
```

If you published Keystone's migrations, publish again to get the new ones (`00004`, `00005`, `00006`). This doesn't overwrite files you already have.

```bash
php artisan vendor:publish --tag=keystone-migrations
php artisan migrate
```

**The schema is now the same whether multi-tenancy is on or off.** Every install has a nullable `tenant_id` on your users table, `roles`, `permissions`, and both assignment pivots, with tenant-aware unique keys. In single-tenant mode the columns stay NULL and Keystone ignores them.

- `2024_01_01_00005_make_tenant_schema_uniform` adds those columns and keys to installs created in single-tenant mode. It runs whatever the setting is, keeps every existing row's `tenant_id` NULL, and changes nothing on a schema that already has them. It adds columns and swaps four unique keys, so on large tables run it in a maintenance window.
- `2024_01_01_00006_drop_redundant_tenant_indexes` drops the index on `tenant_id` alone from `roles`, `permissions`, and both pivots, where a unique key leading with `tenant_id` already covers it. The users table's index stays. Dropping an index takes a brief metadata lock on MySQL.
- In single-tenant mode, a duplicate role or permission name is now rejected by Keystone with `RoleAlreadyExists` / `PermissionAlreadyExists` (409 over HTTP) instead of by a database unique-key error, because `tenant_id` is now part of the key and NULLs are distinct in unique keys. If you caught `QueryException` for duplicates, catch these instead.

### 4. Management API: no privilege escalation

**Affects:** apps that expose Keystone's management API (`routes/api.php`) to callers who aren't super-admins.

A caller who isn't a super-admin can now only grant access they already have:

| Endpoint | Rule |
|---|---|
| `POST` / `PUT /api/users/{user}/roles` | For each role the user doesn't already hold, the caller must hold every permission that role grants |
| `POST` / `PUT /api/users/{user}/permissions` | For each permission the user doesn't already hold directly, the caller must hold it |
| `POST` / `PUT /api/roles/{role}/permissions`, `POST /api/roles` with `permissions` | For each permission not already on the role, the caller must hold it |
| The super-admin role | Only a super-admin can grant it, remove it from a user (including with `PUT`), or change its permissions |

A violation returns **403**, the message names the role or permission, and nothing in the request is applied.

**What to do:**

1. **Audit existing grants.** Before this release, any holder of `assign-roles` could grant themselves `super-admin`. List who holds it and confirm each one:

   ```php
   User::role(config('keystone.rbac.super_admin_role'))->get(['id', 'email']);
   ```

2. Make sure the admins who use the API **hold the permissions they hand out**, usually by giving their admin role those permissions. Otherwise, have a super-admin perform those grants.
3. If your app needs different delegation rules, write your own endpoint. The trait (`$user->assignRole()`), the services, and the console commands are **not** restricted, so trusted code can grant anything.

### 5. Management API: tenant callers can't modify global rows

**Affects:** multi-tenant apps whose tenant admins manage **global** roles or permissions (`tenant_id` NULL) through the API.

A caller who belongs to a tenant now gets **403** from `DELETE /api/roles/{role}`, `DELETE /api/permissions/{permission}`, and `POST` / `PUT /api/roles/{role}/permissions` when the target is global. Global rows are shared by every tenant, so only callers with no tenant can change them. Tenant callers can still read global rows and assign global roles to their users.

**What to do:** manage global roles as a user with no tenant, or through the console commands.

### 6. Super-admin must be a global role

**Affects:** multi-tenant apps.

Only a **global** role named `keystone.rbac.super_admin_role` (default `super-admin`) now makes a user a super-admin. Tenant roles with that name no longer bypass anything, and creating or renaming one throws `BSPDX\Keystone\Exceptions\ReservedRoleName` (the API returns 422).

**What to do:** look for tenant-scoped roles with the reserved name. Each one was an app-wide bypass before this release, so audit who held it:

```php
use BSPDX\Keystone\Models\KeystoneRole;

KeystoneRole::withoutTenant()
    ->where('name', config('keystone.rbac.super_admin_role'))
    ->whereNotNull('tenant_id')
    ->get();
```

Rename those roles (for example to `tenant-admin`) and grant them the permissions they actually need.

### 7. Gate callback defers to your policies

**Affects:** apps with a policy method or `Gate::define` ability whose name matches a Keystone permission.

Keystone's `Gate::before` callback used to return `false` when a same-named permission existed and the user lacked it, which overrode your policy. That applied even when the permission belonged to another tenant. The callback now returns `true` for super-admins and held permissions, and `null` otherwise, so your policy or defined gate decides.

**What to do:** if you relied on the permission check overriding a permissive policy, check the permission inside the policy:

```php
public function update(User $user, Post $post): bool
{
    return $user->hasPermissionTo('update-post') && $user->id === $post->user_id;
}
```

If no policy or gate exists for the ability, a user without the permission is still denied, as before.

### 8. Gate callback runs in queues and commands

**Affects:** apps that call `can()` / `Gate::allows()` in queued jobs, scheduled tasks, Artisan commands, or tinker.

Keystone used to skip Gate registration whenever the app ran in the console outside tests, so `can('some-permission')` in a queue worker ignored Keystone. It's now registered everywhere, and those checks now honor Keystone permissions. If a job worked only because of the old behavior, update it.

### 9. Default guard is `auth.defaults.guard`

**Affects:** apps whose `auth.defaults.guard` isn't `web` (for example `api` or `sanctum`).

Everywhere a guard isn't given, Keystone used a mix of hard-coded `'web'` and the auth default. Checks (`hasRole()`, `hasPermissionTo()`, …) always used `'web'`, while `keystone:make-permission` used your default guard, so permissions created by the command never matched a check. Now everything uses `auth.defaults.guard`. Guard parameters are now `?string $guard = null`.

Names given to assignment methods (`assignRole('editor')`) now resolve only among rows on the default guard. Pass a model instance to assign a role or permission on another guard. The console commands (`--guard`) and the management API resolve a role's permission names on that role's guard.

**What to do:** if your default guard isn't `web`, either move existing rows to it:

```sql
UPDATE roles SET guard_name = 'api' WHERE guard_name = 'web';
UPDATE permissions SET guard_name = 'api' WHERE guard_name = 'web';
```

or pass the guard explicitly: `$user->hasPermissionTo('edit-posts', 'web')`.

### 10. `hasDirectPermission()` is literal for super-admins

`$user->hasDirectPermission('x')` now returns true only when the user actually holds `x` directly, matching `hasRole()` since 0.11.0. `AuthorizationService::userHasDirectPermission()` still returns true for super-admins. If you used `hasDirectPermission()` to mean "may do this", use `hasPermissionTo()` or `can()` instead.

### 11. Demo users moved to `KeystoneDemoUserSeeder`

`KeystoneSeeder` now creates only roles and permissions. The demo users (`superadmin@example.com` and so on, password `password`) are in `KeystoneDemoUserSeeder`, which refuses to run in production:

```bash
php artisan vendor:publish --tag=keystone-seeders
php artisan db:seed --class=KeystoneDemoUserSeeder
```

If an environment was seeded with the old `KeystoneSeeder`, **delete or change the password of those demo accounts.**

### 12. `load_routes` loads the API routes only

With `keystone.load_routes = true`, Keystone now loads only `routes/api.php`. It loads them unless you published `routes/keystone-api.php`, whether or not `routes/keystone-web.php` exists. The demo web routes (`/admin`, `/posts/edit`) are never loaded. If you want them, copy them from `vendor:publish --tag=keystone-routes`. Package-loaded API routes now bind `{role}` / `{permission}` correctly; before, `DELETE /api/roles/{id}` silently did nothing.

### 13. `keystone.feature` middleware removed; new namespaced aliases

`keystone.feature` and `EnsureFeatureEnabled` are gone. Replace any use with your own check:

```php
Route::get('/tenants', ...)->middleware(function ($request, $next) {
    abort_unless(config('keystone.features.multi_tenant'), 404);

    return $next($request);
});
```

Keystone now always registers `keystone.role` and `keystone.permission`. It registers the short `role` and `permission` aliases only when your app hasn't defined them, so it no longer overrides your own middleware. If another package also defines `role` / `permission`, use the namespaced aliases.

### 14. Custom service implementations

**Affects:** only apps that implement Keystone's service interfaces themselves.

- User parameters on `RoleServiceInterface`, `PermissionServiceInterface`, and `AuthorizationServiceInterface` are now `BSPDX\Keystone\Contracts\KeystoneUser` instead of `Authenticatable`.
- `RoleServiceInterface` gains `paginateWithPermissions(int $perPage, ?int $page = null): LengthAwarePaginator`, and `PermissionServiceInterface` gains `paginateWithRoles(int $perPage, ?int $page = null): LengthAwarePaginator`. `getAllWithPermissions()` / `getAllWithRoles()` should order by `name`, then `id`.
- `RoleServiceInterface::create(string $name, ?string $guardName = null)` and `findByName(string $name, ?string $guardName = null, ?string $tenantId = null)`, and the same two methods on `PermissionServiceInterface`: the guard now defaults to `null`, meaning `auth.defaults.guard`. `findByName()` should prefer a tenant row over a global row with the same name.
- `RoleService::delete()` should throw `BSPDX\Keystone\Exceptions\CannotDeleteSuperAdminRole` for the super-admin role. It renders as a 403.
- If you extend `KeystoneRole`, `revokePermissionTo()` is now variadic. See also [section 18](#18-typed-method-signatures).

### 15. Management API response shapes

**Affects:** clients of Keystone's management API.

Every role and permission in a response now has one fixed shape, built by `RoleResource` / `PermissionResource`. The top-level keys (`roles`, `permissions`, `role`, `permission`, `user`, `message`) are unchanged, no `data` wrapper is added, and the `user` objects are unchanged.

Before (`POST /api/roles`, raw model, abbreviated):

```json
{
    "role": {
        "id": 7, "tenant_id": null, "name": "moderator", "guard_name": "web",
        "title": null, "description": null,
        "created_at": "2026-10-24T12:00:00.000000Z", "updated_at": "2026-10-24T12:00:00.000000Z",
        "permissions": [{"id": 3, "name": "edit-posts", "guard_name": "web", "pivot": {"role_id": 7, "permission_id": 3}}]
    }
}
```

After:

```json
{
    "role": {
        "id": 7, "name": "moderator", "guard_name": "web", "title": null, "description": null,
        "permissions": ["edit-posts"],
        "created_at": "2026-10-24T12:00:00.000000Z"
    }
}
```

- `tenant_id`, `updated_at`, `pivot`, and nested permission objects are gone. `role.permissions` is always an array of names.
- `created_at` is ISO 8601 everywhere. The list routes used `Y-m-d H:i:s` before.
- `GET /api/roles` items add `users_count`; `GET /api/permissions` items add `roles` (names). Both now include `title` and `description`.
- Both lists are ordered by name. Pass `per_page` (1–100) and `page` for one page plus `meta` (`current_page`, `per_page`, `last_page`, `total`). Without `per_page` the full list is returned, as before.
- `roles` / `permissions` request arrays are limited to 100 distinct names (422 otherwise), and the example route group applies `throttle:60,1`.

### 16. Management API controllers split by resource

**Affects:** apps with a published `routes/keystone-api.php`, or code that references `RolePermissionController` actions.

URIs, route names, middleware, and responses (apart from [section 15](#15-management-api-response-shapes)) are unchanged. The quickest fix is to re-publish the route file (`php artisan vendor:publish --tag=keystone-routes --force`, then re-apply your edits). Otherwise update each route:

| Old action | New controller and action |
|---|---|
| `RolePermissionController@roles` | `RoleController@index` |
| `RolePermissionController@createRole` | `RoleController@store` |
| `RolePermissionController@deleteRole` | `RoleController@destroy` |
| `RolePermissionController@permissions` | `PermissionController@index` |
| `RolePermissionController@createPermission` | `PermissionController@store` |
| `RolePermissionController@deletePermission` | `PermissionController@destroy` |
| `RolePermissionController@assignRoles` | `UserRoleController@store` |
| `RolePermissionController@syncRoles` | `UserRoleController@update` |
| `RolePermissionController@assignPermissions` | `UserPermissionController@store` |
| `RolePermissionController@syncPermissions` | `UserPermissionController@update` |
| `RolePermissionController@assignPermissionsToRole` | `RolePermissionController@store` |
| `RolePermissionController@syncRolePermissions` | `RolePermissionController@update` |
| `RolePermissionController@userRolesPermissions` | `UserAccessController@show` |

`RolePermissionController` now serves only a role's permissions. A route still pointing at an old action fails because the method doesn't exist, rather than running different code.

### 17. Keystone exceptions render as client errors

**Affects:** apps that let Keystone's exceptions reach the HTTP layer, including from their own endpoints.

`KeystoneException` now renders itself as `{"message": …}` with a client-error status, and implements `ShouldntReport`:

| Exception | Status (was) |
|---|---|
| `RoleAlreadyExists`, `PermissionAlreadyExists` | 409 (500) |
| `ReservedRoleName` | 422 (500) |
| `CannotDeleteSuperAdminRole` | 403 (403, same message) |

They're no longer written to your error log. To render them differently, register your own `render` callback for them in `bootstrap/app.php`; it takes precedence. Console and queue contexts are unaffected.

### 18. Typed method signatures

**Affects:** apps that extend `KeystoneRole` / `KeystonePermission` or override a `HasKeystone` method, and code passing unusual argument types.

Every public method on `HasKeystone`, `KeystoneRole`, and `KeystonePermission` now declares its types: role arguments `string|int|KeystoneRole|iterable`, permission arguments `string|int|KeystonePermission|iterable`, guards `?string`, tenant arguments `?string` (`belongsToTenant`) and `string` (`forTenant`), mutating methods return `static`, checks return `bool`, and name lists and `getAllPermissions()` return a `Collection`.

- Overrides must use compatible signatures.
- Names, integer IDs, models, arrays, and collections are accepted as before. Any other top-level argument is now a `TypeError` (it was an `InvalidArgumentException`, or a silent `false` from `hasRole()`). A wrong type inside an array passed to an assignment method is still an `InvalidArgumentException`.

### 19. Switching multi-tenancy on or off

The schema no longer depends on the setting ([section 3](#3-update-the-package-and-migrations)), so switching needs no migration, only a one-time data step.

**Turning it on** for an install that already has data: set `KEYSTONE_MULTI_TENANT=true`, give your users their `tenant_id`, then run:

```bash
php artisan keystone:enable-multi-tenancy
```

It stamps every assignment that has no tenant with its user's tenant, so tenant-scoped `removeRole()` / `syncRoles()` act on it. Roles and permissions stay global. It asks for confirmation (`--force` skips it), because assignments stored without a tenant on purpose are moved too. This command no longer changes the schema.

**Turning it off** is now supported:

```bash
php artisan keystone:disable-multi-tenancy
```

It sets every Keystone `tenant_id` to NULL in one transaction and leaves your users table alone. If two tenants (or a tenant and the global rows) use the same role or permission name, it lists them and changes nothing; rename or merge them first. A user holding the same role in two tenant contexts ends up with one assignment. Then set `KEYSTONE_MULTI_TENANT=false`. If you only change the setting, same-named roles from different tenants all become visible.
