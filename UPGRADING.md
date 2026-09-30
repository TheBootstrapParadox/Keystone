# Upgrading

## Upgrading to the 1.0 release candidate

This release closes several security holes in Keystone's authorization. Fixing them changed behavior you may rely on. Work through each section that applies to your app. [CHANGELOG.md](CHANGELOG.md) has the full list of changes.

- [1. Update the package and migrations](#1-update-the-package-and-migrations)
- [2. Management API: no privilege escalation](#2-management-api-no-privilege-escalation)
- [3. Management API: tenant callers can't modify global rows](#3-management-api-tenant-callers-cant-modify-global-rows)
- [4. Super-admin must be a global role](#4-super-admin-must-be-a-global-role)
- [5. Gate callback defers to your policies](#5-gate-callback-defers-to-your-policies)
- [6. Gate callback runs in queues and commands](#6-gate-callback-runs-in-queues-and-commands)
- [7. Default guard is `auth.defaults.guard`](#7-default-guard-is-authdefaultsguard)
- [8. `hasDirectPermission()` is literal for super-admins](#8-hasdirectpermission-is-literal-for-super-admins)
- [9. Demo users moved to `KeystoneDemoUserSeeder`](#9-demo-users-moved-to-keystonedemouserseeder)
- [10. `load_routes` loads the API routes only](#10-load_routes-loads-the-api-routes-only)
- [11. `keystone.feature` middleware removed; new namespaced aliases](#11-keystonefeature-middleware-removed-new-namespaced-aliases)
- [12. Custom service implementations](#12-custom-service-implementations)
- [13. Enabling multi-tenancy on an existing install](#13-enabling-multi-tenancy-on-an-existing-install)

### 1. Update the package and migrations

```bash
composer update bspdx/keystone
```

If you published Keystone's migrations, publish again to get the new upgrade migration. This doesn't overwrite files you already have.

```bash
php artisan vendor:publish --tag=keystone-migrations
php artisan migrate
```

The new migration changes nothing unless multi-tenancy is enabled and your tables were created in single-tenant mode. See [section 13](#13-enabling-multi-tenancy-on-an-existing-install).

### 2. Management API: no privilege escalation

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

### 3. Management API: tenant callers can't modify global rows

**Affects:** multi-tenant apps whose tenant admins manage **global** roles or permissions (`tenant_id` NULL) through the API.

A caller who belongs to a tenant now gets **403** from `DELETE /api/roles/{role}`, `DELETE /api/permissions/{permission}`, and `POST` / `PUT /api/roles/{role}/permissions` when the target is global. Global rows are shared by every tenant, so only callers with no tenant can change them. Tenant callers can still read global rows and assign global roles to their users.

**What to do:** manage global roles as a user with no tenant, or through the console commands.

### 4. Super-admin must be a global role

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

### 5. Gate callback defers to your policies

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

### 6. Gate callback runs in queues and commands

**Affects:** apps that call `can()` / `Gate::allows()` in queued jobs, scheduled tasks, Artisan commands, or tinker.

Keystone used to skip Gate registration whenever the app ran in the console outside tests, so `can('some-permission')` in a queue worker ignored Keystone. It's now registered everywhere, and those checks now honor Keystone permissions. If a job worked only because of the old behavior, update it.

### 7. Default guard is `auth.defaults.guard`

**Affects:** apps whose `auth.defaults.guard` isn't `web` (for example `api` or `sanctum`).

Everywhere a guard isn't given, Keystone used a mix of hard-coded `'web'` and the auth default. Checks (`hasRole()`, `hasPermissionTo()`, …) always used `'web'`, while `keystone:make-permission` used your default guard, so permissions created by the command never matched a check. Now everything uses `auth.defaults.guard`. Guard parameters are now `?string $guard = null`.

Names given to assignment methods (`assignRole('editor')`) now resolve only among rows on the default guard. Pass a model instance to assign a role or permission on another guard.

**What to do:** if your default guard isn't `web`, either move existing rows to it:

```sql
UPDATE roles SET guard_name = 'api' WHERE guard_name = 'web';
UPDATE permissions SET guard_name = 'api' WHERE guard_name = 'web';
```

or pass the guard explicitly: `$user->hasPermissionTo('edit-posts', 'web')`.

### 8. `hasDirectPermission()` is literal for super-admins

`$user->hasDirectPermission('x')` now returns true only when the user actually holds `x` directly, matching `hasRole()` since 0.11.0. `AuthorizationService::userHasDirectPermission()` still returns true for super-admins. If you used `hasDirectPermission()` to mean "may do this", use `hasPermissionTo()` or `can()` instead.

### 9. Demo users moved to `KeystoneDemoUserSeeder`

`KeystoneSeeder` now creates only roles and permissions. The demo users (`superadmin@example.com` and so on, password `password`) are in `KeystoneDemoUserSeeder`, which refuses to run in production:

```bash
php artisan vendor:publish --tag=keystone-seeders
php artisan db:seed --class=KeystoneDemoUserSeeder
```

If an environment was seeded with the old `KeystoneSeeder`, **delete or change the password of those demo accounts.**

### 10. `load_routes` loads the API routes only

With `keystone.load_routes = true`, Keystone now loads only `routes/api.php`. It loads them unless you published `routes/keystone-api.php`, whether or not `routes/keystone-web.php` exists. The demo web routes (`/admin`, `/posts/edit`) are never loaded. If you want them, copy them from `vendor:publish --tag=keystone-routes`. Package-loaded API routes now bind `{role}` / `{permission}` correctly; before, `DELETE /api/roles/{id}` silently did nothing.

### 11. `keystone.feature` middleware removed; new namespaced aliases

`keystone.feature` and `EnsureFeatureEnabled` are gone. Replace any use with your own check:

```php
Route::get('/tenants', ...)->middleware(function ($request, $next) {
    abort_unless(config('keystone.features.multi_tenant'), 404);

    return $next($request);
});
```

Keystone now always registers `keystone.role` and `keystone.permission`. It registers the short `role` and `permission` aliases only when your app hasn't defined them, so it no longer overrides your own middleware. If another package also defines `role` / `permission`, use the namespaced aliases.

### 12. Custom service implementations

**Affects:** only apps that implement Keystone's service interfaces themselves.

- `RoleServiceInterface::create(string $name, ?string $guardName = null)` and `findByName(string $name, ?string $guardName = null, ?string $tenantId = null)`, and the same two methods on `PermissionServiceInterface`: the guard now defaults to `null`, meaning `auth.defaults.guard`.
- `RoleService::delete()` should throw `BSPDX\Keystone\Exceptions\CannotDeleteSuperAdminRole` for the super-admin role. The API only turns that exception into a 403.
- If you extend `KeystoneRole`, `revokePermissionTo()` is now variadic: `revokePermissionTo(...$permissions)`.

### 13. Enabling multi-tenancy on an existing install

Migrations run only once, so turning on `KEYSTONE_MULTI_TENANT` after your Keystone tables exist doesn't add the tenant columns by itself. Run:

```bash
php artisan keystone:enable-multi-tenancy
```

It adds `tenant_id` to your users table, `roles`, `permissions`, and both assignment pivots, and makes every unique key tenant-aware. Existing roles and permissions become global. It's safe to run more than once. Switching back from multi-tenant to single-tenant isn't supported.
