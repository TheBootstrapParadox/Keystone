# Changelog

**Note:** This CHANGELOG was created starting with v0.3.0. Changes prior to this version were not formally documented.

---

## [Unreleased]

Hardening for the 1.0 release candidate. This release contains several **security fixes** and a number of **breaking changes**; read [UPGRADING.md](UPGRADING.md) before updating.

### Added

- `BSPDX\Keystone\Contracts\KeystoneUser` interface, declaring every public method `HasKeystone` adds. See the BREAKING entry below.
- `keystone:enable-multi-tenancy {--force}` command: after turning multi-tenancy on, stamps every assignment that has no tenant with its user's tenant, in one transaction. It changes no schema, roles, permissions, or users.
- `keystone:disable-multi-tenancy {--force}` command: sets every Keystone `tenant_id` back to NULL in one transaction, keeps one row of an assignment held in two tenant contexts, and refuses (changing nothing) when two tenants use the same role or permission name. Switching from multi-tenant back to single-tenant is now supported.
- Migrations `2024_01_01_00004_upgrade_roles_permissions_for_multi_tenant` and `2024_01_01_00005_make_tenant_schema_uniform`, which bring installs created in single-tenant mode to the uniform schema (see Changed), and `2024_01_01_00006_drop_redundant_tenant_indexes`, which drops the index on `tenant_id` alone from `roles`, `permissions`, and both pivots wherever a unique key leading with `tenant_id` covers it.
- `RoleServiceInterface::paginateWithPermissions()` and `PermissionServiceInterface::paginateWithRoles()`.
- `GET /api/roles` and `GET /api/permissions` accept an optional `per_page` (1–100) and `page`, returning one page plus `meta` (`current_page`, `per_page`, `last_page`, `total`). Without `per_page` they return the full list as before.
- `RoleResource` and `PermissionResource` API Resources, and the resource controllers listed under Changed.
- `--tenant=<uuid>` option on `keystone:make-role`, `keystone:make-permission`, `keystone:assign-permission`, and `keystone:unassign-permission`. It creates the role or permission in that tenant, or finds `--to-role` / `--from-role` in that tenant.
- `keystone.role` and `keystone.permission` middleware aliases. They're always registered, even when your app defines its own `role` / `permission` aliases.
- `KeystoneDemoUserSeeder`, which creates the demo users. It refuses to run in `production`.
- `BSPDX\Keystone\Exceptions\` namespace: `KeystoneException` (base), `CannotDeleteSuperAdminRole`, `ReservedRoleName`, `RoleAlreadyExists`, `PermissionAlreadyExists`.
- `hasRole()`, `hasPermissionTo()`, `assignRole()`, `removeRole()`, `givePermissionTo()`, `revokePermissionTo()`, and the sync methods on `HasKeystone` accept integer IDs. `KeystoneRole` permission methods accept them too. (`KeystonePermission` role methods already did.)
- Deleting a user now removes their role and permission assignments in every tenant. Soft-deleted users keep them until force-deleted.

### Changed

- **BREAKING:** The management API enforces **no privilege escalation**. A caller who isn't a super-admin can only:
  - grant a role if they hold every permission that role grants;
  - grant permissions (to a user or a role) they hold themselves.

  Only a super-admin can grant, remove, or change the permissions of the super-admin role. Violations get **403** and change nothing, even for the other items in the same request. Roles or permissions the target already has are not checked. The trait, services, and commands are not restricted. ([UPGRADING](UPGRADING.md#4-management-api-no-privilege-escalation))
- **BREAKING:** In multi-tenant mode, a caller with a tenant gets **403** when deleting a **global** role or permission, or changing a global role's permissions, through the API. Callers with no tenant are unaffected. ([UPGRADING](UPGRADING.md#5-management-api-tenant-callers-cant-modify-global-rows))
- **BREAKING:** In multi-tenant mode, only a **global** role named `keystone.rbac.super_admin_role` makes a user a super-admin. Creating or renaming a tenant role to that name throws `ReservedRoleName`, and the API rejects it with 422. ([UPGRADING](UPGRADING.md#6-super-admin-must-be-a-global-role))
- **BREAKING:** The `Gate::before` callback no longer denies. It returns `true` for super-admins and for held permissions, and otherwise returns `null`, so your policies and `Gate::define` abilities decide. Before, if any permission (in any tenant) shared an ability's name, users without it were denied even when a policy allowed them. ([UPGRADING](UPGRADING.md#7-gate-callback-defers-to-your-policies))
- **BREAKING:** The Gate callback is registered in every context, including queue workers, the scheduler, and tinker, instead of only for HTTP requests and tests. `can()` in queued jobs now honors Keystone permissions. ([UPGRADING](UPGRADING.md#8-gate-callback-runs-in-queues-and-commands))
- **BREAKING:** Wherever no guard is given, Keystone now uses `auth.defaults.guard` instead of `'web'`. That covers:
  - `hasRole()`, `hasPermissionTo()`, `hasDirectPermission()`, `hasAny*` / `hasAll*`;
  - `RoleService::create()` / `findByName()` and `PermissionService::create()` / `findByName()`;
  - `PermissionRegistrar::permissionExists()`;
  - model creation, name resolution, the API, and the super-admin check.

  Guard parameters are now `?string $guard = null`. Nothing changes if your default guard is `web`. ([UPGRADING](UPGRADING.md#9-default-guard-is-authdefaultsguard))
- **BREAKING:** `hasDirectPermission()` no longer passes automatically for super-admins, matching the 0.11.0 change to `hasRole()`. `AuthorizationService::userHasDirectPermission()` keeps the bypass. ([UPGRADING](UPGRADING.md#10-hasdirectpermission-is-literal-for-super-admins))
- **BREAKING:** `KeystoneSeeder` no longer creates demo users. Run `KeystoneDemoUserSeeder` for them. ([UPGRADING](UPGRADING.md#11-demo-users-moved-to-keystonedemouserseeder))
- **BREAKING:** With `keystone.load_routes` enabled, Keystone loads only `routes/api.php`. It no longer loads the demo web routes (`/admin`, `/posts/edit`). The API routes load when `routes/keystone-api.php` isn't published, regardless of `routes/keystone-web.php`. ([UPGRADING](UPGRADING.md#12-load_routes-loads-the-api-routes-only))
- **BREAKING:** Role and permission names given to assignment methods now resolve only among rows on the default guard. Pass a model instance to assign a role or permission on another guard. ([UPGRADING](UPGRADING.md#9-default-guard-is-authdefaultsguard))
- **BREAKING (custom implementers only):** `RoleServiceInterface::create()` / `findByName()`, `PermissionServiceInterface::create()` / `findByName()`, and `KeystoneRole::revokePermissionTo(...$permissions)` have new signatures. ([UPGRADING](UPGRADING.md#14-custom-service-implementations))
- **BREAKING:** Your user model must declare `implements KeystoneUser` alongside `use HasKeystone;`. The services take a `KeystoneUser` where they took an `Authenticatable`, and a configured user model without the interface makes the management API and commands throw a `LogicException` naming the class. A logged-in user whose model doesn't implement it gets 403 from the `role` / `permission` middleware and is left to your policies by the Gate. Keystone checks `instanceof KeystoneUser` instead of `method_exists()`. ([UPGRADING §1](UPGRADING.md#1-add-implements-keystoneuser-to-your-user-model))
- **BREAKING:** The minimum Laravel version is 12.41 (`laravel/framework: ^12.41|^13.0`). Keystone relies on `Cache::memo()` (12.9) and `Connection::afterRollBack()` (12.32). ([UPGRADING §2](UPGRADING.md#2-laravel-1241-or-later))
- **BREAKING:** The schema is the same whether multi-tenancy is on or off. Every install has a nullable `tenant_id` on the users table, `roles`, `permissions`, and both assignment pivots, with tenant-aware unique keys; in single-tenant mode the columns stay NULL and Keystone neither writes nor filters by them. The migrations no longer read `keystone.features.multi_tenant`, and `00005` upgrades installs created in single-tenant mode. As a result, a duplicate role or permission name in single-tenant mode is rejected by Keystone (`RoleAlreadyExists` / `PermissionAlreadyExists`) instead of by a database unique key. ([UPGRADING §3](UPGRADING.md#3-update-the-package-and-migrations), [§19](UPGRADING.md#19-switching-multi-tenancy-on-or-off))
- **BREAKING:** Keystone's exceptions render as JSON client errors and are no longer reported (`ShouldntReport`): 409 for `RoleAlreadyExists` / `PermissionAlreadyExists`, 422 for `ReservedRoleName`, 403 for `CannotDeleteSuperAdminRole` (unchanged message). Before, everything but the last was a 500. This applies wherever they reach the HTTP layer, including your own endpoints. ([UPGRADING §17](UPGRADING.md#17-keystone-exceptions-render-as-client-errors))
- **BREAKING:** Every role and permission in a management API response has one fixed shape, built by `RoleResource` / `PermissionResource`: a role is `id`, `name`, `guard_name`, `title`, `description`, `permissions` (names), `created_at`; a permission is the same without `permissions`. `tenant_id`, `updated_at`, pivot data, and nested permission objects are no longer returned, and `created_at` is ISO 8601 on every route. ([UPGRADING §15](UPGRADING.md#15-management-api-response-shapes))
- **BREAKING (custom implementers only):** `RoleServiceInterface` and `PermissionServiceInterface` gain `paginateWithPermissions()` / `paginateWithRoles()`, and their user parameters are `KeystoneUser`. ([UPGRADING §14](UPGRADING.md#14-custom-service-implementations))
- **BREAKING:** `RolePermissionController` is split into `RoleController`, `PermissionController`, `UserRoleController`, `UserPermissionController`, `RolePermissionController` (a role's permissions), and `UserAccessController`, using only resource action names. URIs, route names, and middleware are unchanged; published route files must be updated. ([UPGRADING §16](UPGRADING.md#16-management-api-controllers-split-by-resource))
- **BREAKING:** Public methods on `HasKeystone`, `KeystoneRole`, and `KeystonePermission` declare parameter and return types (role arguments `string|int|KeystoneRole|iterable`, permission arguments `string|int|KeystonePermission|iterable`, mutating methods return `static`). Accepted values are unchanged, but overrides must match and an unsupported top-level argument is now a `TypeError`. `KeystonePermission::hasRole()` accepts an iterable (any of them). ([UPGRADING §18](UPGRADING.md#18-typed-method-signatures))
- `GET /api/roles` and `GET /api/permissions` are ordered by name, then `id`. `users_count` is counted for the listed page only, so a page costs the same number of queries whatever its size.
- `roles` / `permissions` arrays in API requests hold at most 100 distinct names (422 otherwise).
- The example API route group applies `throttle:60,1`.
- `POST /api/roles` creates the role and attaches its permissions in one transaction.
- Each role or permission name in an API request is looked up once, shared by validation and the action.
- `RoleService::findByName()` / `PermissionService::findByName()` are deterministic: when a tenant row and a global row both match, the tenant row is returned.
- The Gate callback reads the permission list from the cache store at most once per request, queued job, or command (`Cache::memo()`), instead of once per `can()`. A long-lived worker still sees changes made by other processes in its next job.
- Keystone throws a `LogicException` naming `keystone.user.model` and `auth.providers.users.model` when neither is set, and no longer references `App\Models\User`.
- Fresh installs no longer create an index on `tenant_id` alone on `roles`, `permissions`, or the pivots; the tenant-aware unique keys cover it.
- API validation is tenant- and guard-aware:
  - A role or permission name only needs to be unique within the caller's tenant (or among global rows) on the same guard. Tenant B can now create `editor` when tenant A already has one.
  - Names passed to assignment endpoints must resolve in the **target's** tenant; otherwise the response is 422 (was 404).
  - `guard_name` must be a configured guard.
- `GET /api/roles` counts only the caller's tenant in `users_count` for tenant callers, runs one count query instead of one per role, and returns `created_at: null` for rows without timestamps instead of erroring.
- A user's roles and permissions, and a role's permissions, no longer depend on who is logged in. The relationships ignore the logged-in caller's tenant scope and rely on the assignment's own tenant. The `User::role()` query scope follows the same rule.
- Permission checks and `getAllPermissions()` eager-load role permissions instead of querying once per role. The Gate callback uses the cached permission list, so abilities that aren't permission names cost no query.
- `RoleService::delete()` throws `CannotDeleteSuperAdminRole` instead of a generic `\Exception`. The API catches only that exception, so other errors are no longer returned as 403 with the raw message.
- Console commands look a user up by primary key only when the identifier looks like one (digits, UUID, or ULID), then by email. UUID- and ULID-keyed user models now work.
- In multi-tenant mode, commands without `--tenant` only resolve **global** roles for `--to-role` / `--from-role`. Before, they could pick another tenant's role with the same name.
- The `role` and `permission` middleware aliases are registered only if your app hasn't defined them.
- `keystone-seeders` publishes only `KeystoneSeeder.php` and `KeystoneDemoUserSeeder.php`. It no longer publishes this repository's `DatabaseSeeder.php`.
- Fresh installs no longer create the unused `deleted_at` columns on the assignment pivots (assignments are always hard-deleted). `role_has_permissions` rows now get timestamps.
- A `HasUlids` user model now gets a ULID `model_id` column on the pivots (fresh installs).
- The distributed package no longer includes the development app skeleton, tests, or tooling. `CHANGELOG.md` is now included.

### Fixed

- `KeystoneRole::revokePermissionTo()` removed only the first permission it was given. This also broke `PermissionService::removeFromRole()`, `keystone:assign-permission --remove`, and `keystone:unassign-permission --from-role` (including `--all`).
- In multi-tenant mode, assignments stored with a NULL tenant (for example, made before the user joined a tenant) were invisible to users with a tenant. `wherePivotIn('tenant_id', [$tenant, null])` never matches NULL.
- With `keystone.load_routes` enabled, `{role}` and `{permission}` weren't bound: `DELETE /api/roles/{id}` returned 200 and deleted nothing, and `POST /api/roles/{role}/permissions` returned 500. The route file now adds `SubstituteBindings` itself.
- Rolling back the users-table migration dropped `tenant_id` even when your app created that column before Keystone. It now drops only a column Keystone added, recognized by its `keystone_users_tenant_id_index` index.
- The pivot `tenant_id` backfill used MySQL-only `UPDATE … JOIN` syntax and matched `model_type LIKE '%User'`. It now uses a correlated subquery that works on SQLite, MySQL/MariaDB, and PostgreSQL, and matches your user model's morph class.
- In multi-tenant mode, duplicate **global** roles or permissions could be created, because unique keys treat NULL tenants as distinct. Creating or renaming onto an existing name, guard, and tenant now throws `RoleAlreadyExists` / `PermissionAlreadyExists`.
- Loaded `permissions` / `roles` relations on `KeystoneRole` and `KeystonePermission` weren't refreshed after `syncPermissions()`, `assignRole()`, `syncRoles()`, or `removeRole()`.
- `PermissionRegistrar::refresh()` registered a duplicate Gate callback on every call.
- `syncPermissions()` on a user deleted the user's direct permissions before resolving the new names, so an unknown name (for example a typo passed to `keystone:assign-permission --to-user=… --sync`) threw after the permissions were already gone. `syncRoles()` and `syncPermissions()` now resolve every name first and replace the assignments in one transaction, so a failure leaves the user unchanged.
- `removeRole()`, `syncRoles()`, `revokePermissionTo()`, and `syncPermissions()` matched pivot rows by `$this->id`, so on a user model whose key column isn't `id` they silently removed nothing. Every pivot query now uses `getKey()`.
- The 0.11.0 entry below was dated 2027 instead of 2026.

### Removed

- **BREAKING:** The `keystone.feature` middleware and `BSPDX\Keystone\Http\Middleware\EnsureFeatureEnabled`. `multi_tenant` is the only feature flag and was never used to gate routes. ([UPGRADING](UPGRADING.md#13-keystonefeature-middleware-removed-new-namespaced-aliases))
- `MIGRATING-TO-AUTHKIT-0.4.0.md` (obsolete).

### Security

- **Any holder of `assign-roles` could grant themselves (or anyone) the `super-admin` role** through `POST /api/users/{user}/roles`. More generally, API callers could grant access they didn't have. Now blocked by the no-escalation rules above. **Audit who holds `super-admin`** if you exposed the management API.
- **A tenant admin could mint an application-wide super-admin** by creating a tenant role named `super-admin` (with `create-roles`) and assigning it (with `assign-roles`). Only global roles count now, and the name is reserved in tenants. **Check for tenant-scoped roles with the super-admin name** (see UPGRADING.md).
- **Tenant admins could delete or rewrite global roles and permissions** that every tenant shares, through `DELETE /api/roles/{role}`, `DELETE /api/permissions/{permission}`, and `POST`/`PUT /api/roles/{role}/permissions`. Now 403.
- **`hasAllRoles(['a', 'b'])` (array form) and `AuthorizationService::userHasAllRoles($user, ['a', 'b'])` returned true when the user held *any* of the roles.** They now require all of them.
- **A permission named like a policy ability, in any tenant, overrode that policy for every user.** A tenant admin could break authorization app-wide by creating, for example, an `update` permission. The Gate callback no longer denies and only considers the user's own permissions.
- API validation no longer reveals whether a role or permission name exists in another tenant.
- `GET /api/roles` no longer reveals how many users in other tenants hold a role.
- `KeystoneSeeder` no longer creates accounts with the password `password`. `KeystoneDemoUserSeeder` refuses to run in production.

### Testing

- The suites now run the package's real migrations (plus a test-only users table) instead of copies. With the uniform schema, both suites run against the same tables, and the single-tenant suite no longer skips multi-tenant-only tests.
- New coverage: API security (escalation, global-row writes, tenant-aware validation, listing), response shapes, pagination, request limits, the rate limit, the resource controllers, exception rendering, the Gate callback and its per-request cache reads, the default guard, caller-independent relationships, integer IDs, typed signatures, the `KeystoneUser` contract, all-or-nothing sync, custom primary keys, user deletion, route loading, the uniform-schema migrations (including a rebuilt pre-change single-tenant schema), redundant index removal, the enable and disable commands, duplicate guards, seeders, schema, `--tenant`, and middleware aliases.
- ✅ Multi-tenant suite: 294 tests passing (892 assertions). Up from 106.
- ✅ Single-tenant suite: 204 tests passing (695 assertions). Up from 38.
- CI also runs both suites on MySQL 8.4 and PostgreSQL 17. On MySQL, the `ddl` group is excluded because MySQL commits schema changes implicitly.

### Breaking Changes

See [UPGRADING.md](UPGRADING.md) for step-by-step instructions. Summary:

| Change | Who is affected |
|---|---|
| API no-escalation rules | Apps exposing the management API to callers who aren't super-admins |
| Tenant callers can't modify global rows | Multi-tenant apps whose tenant admins manage global roles through the API |
| Super-admin role must be global | Multi-tenant apps with a tenant-scoped role named like `super_admin_role` |
| Gate callback returns `null` instead of `false` | Apps with a policy or `Gate::define` ability that shares a permission's name |
| Gate callback registered in console | Apps whose queued jobs or commands relied on `can()` ignoring Keystone permissions |
| Default guard is `auth.defaults.guard` | Apps whose default guard isn't `web` |
| Literal `hasDirectPermission()` | Code relying on super-admins passing `hasDirectPermission()` |
| Seeder split | Anyone running `KeystoneSeeder` for demo users |
| `load_routes` loads API routes only | Apps relying on the auto-loaded demo web routes |
| `keystone.feature` removed | Routes using `keystone.feature:...` |
| Service interface signatures | Custom implementations of `RoleServiceInterface` / `PermissionServiceInterface` |
| `implements KeystoneUser` required | Every app: the user model, and any other model holding roles |
| Laravel 12.41 minimum | Apps on Laravel 12.0–12.40 |
| One schema in both modes | Installs created in single-tenant mode (new migrations), and code catching `QueryException` for duplicate names |
| Exceptions render as 409 / 422 / 403 | Apps relying on a 500, or on these exceptions being reported |
| Fixed API response shapes | Clients reading `tenant_id`, `updated_at`, `pivot`, or permission objects, or the old list `created_at` format |
| Service interface methods and `KeystoneUser` parameters | Custom service implementations |
| Controller split | Apps with a published `routes/keystone-api.php` or references to `RolePermissionController` actions |
| Typed public signatures | Subclasses and overrides of the trait or models; calls with unsupported argument types |

#### Migration Guide

1. Read [UPGRADING.md](UPGRADING.md) and work through each section that applies to you.
2. Add `implements KeystoneUser` next to `use HasKeystone;` on your user model ([§1](UPGRADING.md#1-add-implements-keystoneuser-to-your-user-model)).
3. Update Laravel to 12.41 or later if you're on an earlier 12.x ([§2](UPGRADING.md#2-laravel-1241-or-later)).
4. Re-publish migrations if you published them (`php artisan vendor:publish --tag=keystone-migrations`) to get `00004`–`00006`, then run `php artisan migrate`. On large tables, run it in a maintenance window ([§3](UPGRADING.md#3-update-the-package-and-migrations)).
5. Re-publish or update `routes/keystone-api.php` for the new controllers ([§16](UPGRADING.md#16-management-api-controllers-split-by-resource)), and update API clients for the new response shapes ([§15](UPGRADING.md#15-management-api-response-shapes)).
6. Custom service implementations: add the pagination methods and take `KeystoneUser` ([§14](UPGRADING.md#14-custom-service-implementations)). Overrides of trait or model methods: match the new signatures ([§18](UPGRADING.md#18-typed-method-signatures)).
7. If you use the management API, give your admin roles the permissions they hand out, or perform those grants as a super-admin.
8. If your default guard isn't `web`, update existing rows' `guard_name` or pass the guard explicitly.
9. Replace `keystone.feature:` middleware and any reliance on demo users from `KeystoneSeeder`.

---

## [0.11.0] - 2026-09-29

### Added

- `KeystoneRole::findByNameForTenant($name, $tenantId)` and `KeystonePermission::findByNameForTenant($name, $tenantId)` look up a role or permission by name as seen from a given tenant. They ignore the caller's scope, and prefer the tenant's own row over a global one.
- `PUT /api/users/{user}/roles` (`api.users.roles.sync`), `PUT /api/users/{user}/permissions` (`api.users.permissions.sync`), and `PUT /api/roles/{role}/permissions` (`api.roles.permissions.sync`). Each **replaces** the set; send `[]` to clear it. They use the same `permission:` middleware as their `POST` counterparts.
- `AuthorizationServiceInterface::syncRolesForUser()` and `syncPermissionsForUser()` replace a user's roles or direct permissions.

### Changed

- **BREAKING:** In multi-tenant mode, role and permission **names** now resolve in the tenant of whatever receives them: the target user for `assignRole` / `removeRole` / `syncRoles` / `givePermissionTo` / `revokePermissionTo` / `syncPermissions` (including through the services, API and commands), the role for `KeystoneRole` permission methods, and the permission for `KeystonePermission` role methods. The receiver's own tenant row wins over a global row with the same name. A receiver with no tenant only matches global rows. A name that exists only in another tenant now throws `ModelNotFoundException` (404 through the API) instead of attaching that tenant's row. Single-tenant installs are unaffected.
- **BREAKING:** `hasRole()`, `hasAnyRole()`, and `hasAllRoles()` on `HasKeystone` no longer pass automatically for super-admins. They now return `true` only for roles the user actually holds. Before, a super-admin "had" every role, including role names that don't exist. The super-admin bypass is unchanged everywhere access is enforced: permission checks, `can()` / `Gate`, the `role:` and `permission:` middleware, and `AuthorizationService::userHasRole()` / `userHasAnyRole()` / `userHasAllRoles()`.
- **BREAKING:** `POST /api/users/{user}/roles`, `POST /api/users/{user}/permissions`, and `POST /api/roles/{role}/permissions` now **add** to the existing set. Before, they replaced it. Already-held items are not duplicated, and an empty array is rejected with 422.
- **BREAKING:** `AuthorizationServiceInterface::assignRolesToUser()` / `assignPermissionsToUser()` now **add** instead of replacing.
- **BREAKING (custom implementers only):** `AuthorizationServiceInterface` has two new methods, `syncRolesForUser()` and `syncPermissionsForUser()`.
- **BREAKING:** `KeystonePermission::forTenant($tenantId)` now returns only that tenant's permissions. It no longer includes global permissions (`tenant_id = NULL`), and it no longer removes the automatic tenant scope. This makes it match `KeystoneRole::forTenant()`, which already behaved this way. Both scopes now also qualify `tenant_id` with the table name, to avoid ambiguous-column errors in joined queries.
- The permission-list cache key is now `keystone.permissions.all.v2`, and the cache stores plain `name`/`guard_name` arrays instead of serialized Eloquent models. Entries under the old `keystone.permissions.all` key are ignored and expire on their own.

### Fixed

- Assigning roles or permissions **by name** in multi-tenant mode could attach **another tenant's** row with the same name. Names were resolved in the logged-in caller's tenant scope, so a caller with no tenant (a global admin, a console command, or a queued job) matched rows from every tenant and took whichever came first. A name existing both in the target's tenant and globally also gave an arbitrary result. Reported in PR #2 review.
- A super-admin no longer "has" nonexistent roles: `hasRole('typo-role')` returned `true`, which hid typos and gave wrong identity answers (badges, dashboards, user lists).
- The `keystone.permissions.all.v2` cache is now cleared automatically whenever a `KeystonePermission` is created, updated, or deleted, whether through the model, `PermissionService`, the API, the console commands, or the seeder. Before this, only the console commands cleared it, so `PermissionRegistrar::permissionExists()` / `getAllPermissionNames()` could return a stale list for up to `rbac.cache_expiration` (24 hours by default). Bulk query updates and deletes (`KeystonePermission::query()->update()/delete()`) bypass model events. Call `CacheServiceInterface::clearPermissionCache()` after those.
- The `role:` and `permission:` middleware no longer call `redirect()->route('login')` for unauthenticated requests. That route isn't defined by Keystone, so apps without a route named `login` got a 500 `RouteNotFoundException`. They now throw `Illuminate\Auth\AuthenticationException`, the same as Laravel's `auth` middleware, and the app's own guest handling decides the response. JSON requests get **401**. Browser requests are redirected via the app's `redirectGuestsTo` / `AuthenticationException::redirectUsing()` callback, or to the `login` route if one exists, and the intended URL is now remembered. Web apps with a `login` route see the same redirect as before.
- `/users/{user}/...` API endpoints (`assignRoles`, `syncRoles`, `assignPermissions`, `syncPermissions`, `userRolesPermissions`) returned 404 for every user when the consuming application defined its own `{user}` route binding (`Route::model('user', ...)` or `Route::bind('user', ...)`). The bound model was converted to its JSON string and then looked up as a user ID. The controller now uses the bound user model as it is, and still applies the cross-tenant 404 check to it.
- The permission list cache (`PermissionRegistrar::permissionExists()` / `getAllPermissionNames()`) could keep stale data for the full cache TTL when permissions changed inside a database transaction. It is now also cleared after the transaction commits or rolls back. Before, a concurrent request could refill it from the pre-commit rows, or the transaction itself could fill it with rows that were then rolled back.

### Removed

- The no-op `KeystonePermission::forgetCachedPermissions()` (an unimplemented TODO), and the `HasKeystone` code that cleared an unused `user_permissions_{id}` cache key after every role/permission change. Both methods were `protected` and had no observable effect. If your User model overrode `forgetCachedPermissions()`, Keystone no longer calls it.

### Security

- **Management API `/api/users/{user}/…` routes acted on the authenticated caller instead of the user in the URL.** `RolePermissionController` type-hinted the `{user}` parameter as the `Authenticatable` interface, which route model binding can't resolve. Laravel filled it with the logged-in user instead. Any caller holding `assign-roles` could therefore grant **themselves** any role, including `super-admin`, via `POST /api/users/{anyone}/roles`. `POST /api/users/{user}/permissions` had the same flaw, and `GET /api/users/{user}/roles-permissions` always returned the caller's own data. The controller now resolves `{user}` against the configured user model (`keystone.user.model`, else `auth.providers.users.model`) and returns **404** for unknown IDs. In multi-tenant mode, a caller who has a tenant gets **404** for users in other tenants. Keystone does not register a global `{user}` route binding, so your app's own bindings are untouched. Non-breaking. **If you registered the example API routes, audit role and permission assignments for accounts that granted themselves access**, for example `super-admin` held by users who only had `assign-roles`.

### Testing

- Added commit and rollback coverage for permission cache invalidation in `tests/Unit/Services/PermissionRegistrarTest.php`.
- Added coverage to `tests/Feature/UserRouteBindingTest.php` for app-level `{user}` bindings, including a check that a tenant caller still gets a 404 for a user in another tenant when the app binds `{user}`.
- All 106 tests passing (259 assertions); single-tenant suite passing (38 tests, 4 skipped).

### Breaking Changes

#### `KeystonePermission::forTenant()` excludes globals and respects the tenant scope

`forTenant()` now means "this tenant's rows only" on both models. A tenant user calling `forTenant()` with another tenant's ID now gets an empty result instead of that tenant's permissions.

**Migration Guide:**

1. Search your code for `KeystonePermission::forTenant(` (and `->forTenant(` on permission queries).
2. If you wanted **the current user's tenant plus global permissions**, drop `forTenant()`. The automatic tenant scope already gives you exactly that: `KeystonePermission::query()->get()`.
3. If you wanted **a specific tenant plus global permissions**, write it explicitly:
   ```php
   KeystonePermission::withoutTenant()
       ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
       ->get();
   ```
4. If you relied on `forTenant()` to **read another tenant's permissions**, add `withoutTenant()` first: `KeystonePermission::withoutTenant()->forTenant($tenantId)->get()`.

#### "Assign" now adds; use `PUT` / `sync*ForUser()` to replace

The assign endpoints and service methods used to wipe and replace the whole set. They now keep existing assignments.

**Migration Guide:**

1. **Published route files:** if you published `routes/keystone-api.php`, copy the three new `PUT` routes (`api.users.roles.sync`, `api.users.permissions.sync`, `api.roles.permissions.sync`) from the package's `routes/api.php`. Your existing `POST` routes keep working, but they now add.
2. **API clients** that send the full desired set with `POST` and expect everything else to be removed must switch to `PUT` with the same body.
3. **Service callers:** replace `assignRolesToUser()` / `assignPermissionsToUser()` with `syncRolesForUser()` / `syncPermissionsForUser()` wherever you relied on replacement.
4. **Custom `AuthorizationServiceInterface` implementations** must add `syncRolesForUser()` and `syncPermissionsForUser()`.

#### Role checks are literal for super-admins

**Migration Guide:**

1. Search your code for `->hasRole(`, `->hasAnyRole(`, and `->hasAllRoles(`, including Blade `@if` checks and policies.
2. If a check is about **identity** ("is this user an editor?"), leave it. It's now correct.
3. If a check is about **access** and super-admins should pass, switch to one of these:
   - `$user->isSuperAdmin() || $user->hasRole('editor')`
   - `app(AuthorizationServiceInterface::class)->userHasRole($user, 'editor')` (keeps the bypass)
   - a permission check (`$user->can('edit-posts')` / `hasPermissionTo()`), which is the recommended way to gate access
   - assigning the role to your super-admins explicitly
4. The `role:` middleware is unaffected. Super-admins still pass role-gated routes.

#### Names resolve in the receiver's tenant

**Migration Guide:**

1. Most apps need no changes. Assignments within a tenant, and assignments of global roles and permissions, behave as before and are now deterministic.
2. If you give a user **with no tenant** a **tenant-specific** role or permission by name, pass the model instead: `$user->assignRole(KeystoneRole::withoutTenant()->forTenant($tenantId)->where('name', 'manager')->firstOrFail())`. Model instances are always used as given.
3. If you relied on a global admin, command, or job attaching a role from a *different* tenant by name, that was the bug. Pass the model explicitly if it's really intended.
4. Code that catches `ModelNotFoundException` from assignment methods may now see it where a wrong-tenant row used to be attached silently.

## [0.10.1] - 2026-08-17

### Fixed

- **BUG:** Single-tenant installs (the default — `KEYSTONE_MULTI_TENANT=false`) threw `SQLSTATE[42S22]: Unknown column 'tenant_id'` on 12 of the 14 public RBAC methods (`roles()`, `permissions()`, `hasRole()`, `assignRole()`, `removeRole()`, `syncRoles()`, `givePermissionTo()`, `hasDirectPermission()`, `revokePermissionTo()`, `syncPermissions()`, `getAllPermissions()`, `KeystoneRole::users()`). `HasKeystone::roles()`/`permissions()` and `KeystoneRole::users()` called `withPivot('tenant_id')` unconditionally, but the `tenant_id` pivot column is only created by the `add_tenant_id_to_pivot_tables` migration when multi-tenancy is enabled. All three relations, plus the write paths in `HasKeystone` that read/write `tenant_id` directly, are now gated behind `config('keystone.features.multi_tenant')`, matching the existing guard already used elsewhere in the trait.
- Exceptions during Gate permission registration (`KeystoneServiceProvider::registerPermissionsWithGate()`) are now logged via `Log::warning()` instead of silently swallowed — a real failure previously looked identical to a normal skip during install/migration, making `can()` silently return `false` for everyone with no trace of why.

### Testing

- Added `tests/Feature/SingleTenantApiTest.php` and a `phpunit.single-tenant.xml` config (`composer test-single-tenant`) that runs the suite with `KEYSTONE_MULTI_TENANT=false` — the existing suite only ever ran with multi-tenancy enabled, which is why this regression shipped in v0.10.0.
- All 47 default-suite tests passing (136 assertions); single-tenant regression suite passing (12 tests, 29 assertions).

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
- `docs/https-setup.md` — documented HTTPS setup for testing passkeys, which no longer exist in this package

### Changed

- **BREAKING:** `routes/web.php` and `routes/api.php` example files now contain only RBAC-protected route examples
- `add_keystone_fields_to_users_table` migration now only adds `tenant_id` — it no longer adds Fortify 2FA columns
- Package description and keywords now describe Keystone as a multi-tenant RBAC package, not an auth orchestration package

### Migration Guide

1. **Remove `KeystoneUser` usage.** If your `User` model extended `KeystoneUser`, change it to extend `Illuminate\Foundation\Auth\User` (or your own base class) and add `use BSPDX\Keystone\Traits\HasKeystone;` directly — see [`docs/USER_MODEL.md`](docs/USER_MODEL.md).
2. **Own your `users` table migration.** If you relied on Keystone's `create_keystone_users_table` migration to create your `users` table, that migration is gone. Existing databases are unaffected, but fresh installs / `migrate:fresh` now need a `users` table from somewhere else — Laravel's own default `create_users_table` migration, or your own.
3. **Set up authentication yourself.** Keystone no longer configures Fortify, Sanctum, or passkeys. Install and configure `laravel/fortify` (and `laravel/sanctum`/`laravel/passkeys` if you need tokens or passkeys) directly, exactly as you would in any Laravel app that doesn't use Keystone.
4. **Remove references to deleted routes/views/commands.** Drop any `keystone-web.php`/`keystone-api.php` includes pointing at the removed login/2FA/passkey/profile/account-deletion routes, delete any published `resources/views/vendor/keystone` customizations, and stop calling `keystone:make-user`/`keystone:change-password`.
5. **Update `config/keystone.php`.** Re-publish with `php artisan vendor:publish --tag=keystone-config --force` and re-apply any custom `rbac.*`/`features.multi_tenant`/`user.model` values — the `passkey`, `two_factor`, `redirects`, `rate_limiting`, `session`, and `profile` sections, and the `features.show_permissions`/`user.primary_key_type`/`user.table_name` keys, are gone. Note that `config('keystone.user.model')`'s default also changed from `\BSPDX\Keystone\Models\KeystoneUser::class` to `null` (resolving instead through `config('auth.providers.users.model')`) — if you have an already-published `config/keystone.php` that still contains the old `KeystoneUser::class` default and haven't re-published it, you'll get runtime errors (a "class not found"-style failure) since that class no longer exists. Re-publishing the config as described above resolves this.
6. **Run `php artisan migrate`.** The `create_keystone_users_table`, `create_passkeys_table`, and `add_auth_preferences_to_users_table` migrations are removed; `add_keystone_fields_to_users_table` now only adds `tenant_id`, not 2FA columns. If you already ran the old migrations in production, write your own follow-up migration to drop the now-unused `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `allow_passkey_login`, `allow_totp_login`, and `require_password` columns — Keystone no longer manages them but won't silently drop your data. Also note that `tenant_id` on the `users` table (and pivot tables) is now always a `uuid` column — previously it was `unsignedBigInteger` when `keystone.user.primary_key_type` was not set to `'uuid'` (that config key is now gone). Hosts on bigint tenant IDs need to plan a data migration before running migrations fresh.
7. **Reimplement any removed `HasKeystone` auth methods you relied on** (`requires2FA()`, `requiresPasskey()`, etc.) using your own authentication package's APIs — Keystone only tracks roles/permissions now.
8. **Note the removal of `docs/https-setup.md`.** It documented HTTPS setup for testing passkeys, which no longer exist in this package — if you referenced it, follow your own authentication package's HTTPS/local-dev guidance instead.

### Testing

- ✅ All 44 tests passing (117 assertions)

---

## [0.9.0] - 2026-06-07

### Added

- `rate_limiting.*` config keys are now wired to real throttling via the `ThrottlesAuthentication` controller concern (`src/Http/Controllers/Concerns/ThrottlesAuthentication.php`), backed by Laravel's `RateLimiter`:
  - `max_login_attempts` + `lockout_duration` throttle passwordless TOTP login (`LoginController::authenticateWithTotp`)
  - `max_2fa_attempts` throttles 2FA code verification (`TwoFactorAuthController::confirm`)
  - `max_passkey_attempts` throttles passkey login and passkey second-factor verification (`PasskeyAuthController::authenticate`, `twofaVerify`)
  - Exceeding a ceiling returns HTTP 429 (JSON) or redirects back with an error (web), reporting the remaining lockout time; `lockout_duration` (minutes) is the decay window; counters clear on success
- Config regression coverage so dead config can no longer silently accumulate:
  - `Tests\Feature\ConfigRegressionTest` — passkey option keys (`timeout`/`user_verification`/`attestation`), `two_factor.recovery_codes_count`, `redirects.login`, feature-flag 404 guards, and `rbac.default_role` / `user.model` (via `keystone:make-user`)
  - `Tests\Feature\RateLimitingTest` — 4 tests proving the `rate_limiting.*` ceilings and lockout behavior
  - `Tests\Unit\Services\PermissionRegistrarTest` — 3 tests for dynamic `rbac.cache_expiration` reads

- `two_factor.qr_code_size` is now wired: `HasKeystone` overrides Fortify's `twoFactorQrCodeSvg()` (which hardcoded 192px) and reads the configured pixel size instead
- `two_factor.window` is now wired: `TwoFactorAuthController::confirm()` and `LoginController::authenticateWithTotp()` both call `setWindow(config('keystone.two_factor.window'))` before every `verifyKey()` call
- `features.account_deletion` endpoint (`DELETE /user/account`): revokes all Sanctum tokens, invalidates the session, and deletes the user record; passkeys cascade via FK; route requires `password-confirm` middleware; returns 404 when disabled
- `features.passkey_2fa` second-factor challenge flow: new `RequirePasskey2FA` middleware (alias `passkey-2fa`) that requires session flag `auth.passkey_2fa_verified_at` when the authenticated user has passkeys; new controller methods `twofaChallengeView`, `twofaChallengeOptions`, and `twofaVerify` on `PasskeyAuthController` at routes `GET /passkey/2fa/challenge`, `POST /passkey/2fa/options`, `POST /passkey/2fa/verify`; new stub view `resources/views/passkeys/2fa-challenge.blade.php`
- `Tests\Feature\TwoFactorConfigTest` — 5 tests covering `qr_code_size` SVG dimensions and `window` passed to `setWindow()`
- `Tests\Feature\AccountDeletionTest` — 4 tests covering 404 when disabled, user deletion, token revocation, web redirect
- `Tests\Feature\PasskeyTwoFactorTest` — 11 tests covering middleware pass-through conditions, 423/redirect enforcement, verify endpoint sets session flag, passkey ownership enforcement, session-bound challenge, and single-use replay protection

- `passkey.*` config keys are now fully wired to runtime behavior:
  - `rp_name` and `rp_id` bridge to Spatie's `passkeys.relying_party.name/id` in `KeystoneServiceProvider::register()`, making Keystone the authoritative config surface for WebAuthn relying-party identity
  - `timeout`, `user_verification`, and `attestation` are applied via a new `BSPDX\Keystone\Actions\GeneratePasskeyRegisterOptionsAction` that extends Spatie's action and reads from Keystone config; the custom action is bound over Spatie's in the service container
  - `allow_multiple` is now enforced in `PasskeyAuthController::registerOptions()` and `store()`: when `false`, any attempt to register a second passkey returns a 422 response with message "Only one passkey is allowed per account."
- `Tests\Feature\PasskeyConfigTest` — 7 focused tests covering config bridge, container binding, and `allow_multiple` enforcement

- `RequirePasswordConfirm` middleware (`src/Http/Middleware/RequirePasswordConfirm.php`) enforcing `profile.require_password_confirm` on all Keystone-owned sensitive profile actions
- `password-confirm` middleware alias registered by `KeystoneServiceProvider`
- `password-confirm` applied to: auth-preferences update, 2FA store/confirm/destroy/recovery-code-regenerate, passkey store/destroy
- When password confirmation is required and expired: API requests receive `423 Unprocessable` with JSON message; web requests redirect to `password.confirm`
- Timeout driven by `keystone.session.password_timeout` (default 10800 seconds / 3 hours)

### Fixed

- `rbac.cache_expiration` is now honored dynamically. `PermissionRegistrar` is a singleton that previously captured the TTL once in its constructor, so any config change after boot (and config changes in tests) never took effect. The TTL is now read from config at each cache write (`PermissionRegistrar::cacheExpiration()`), and an explicit `null` config value falls back to the 86400-second default. Covered by `Tests\Unit\Services\PermissionRegistrarTest` (3 tests).
- `features.show_permissions` now correctly controls whether roles and permissions are loaded and exposed in the profile view. The previous guard used `method_exists($user, 'getRoleNames')` (a Spatie Laravel Permission method that does not exist on `HasKeystone`), causing roles and permissions to never be loaded regardless of the flag value. The guard now uses the Keystone trait's own `$user->roles` relationship and `$user->getAllPermissions()` method.

### Security

- Passkey second-factor verification (`PasskeyAuthController::twofaVerify()`) now enforces passkey ownership: the resolved passkey must belong to the authenticated user, preventing a passkey from another account from setting the `auth.passkey_2fa_verified_at` session flag.
- Passkey second-factor verification is no longer vulnerable to challenge replay or client-controlled WebAuthn options. The challenge generated by `twofaChallengeOptions()` is stored server-side in the session and pulled (single-use) during verification, rather than being accepted from the request body.

### Testing

- ✅ All 109 tests passing (239 assertions)

### Known Issues

- **Passkey route conflict with Fortify**: `laravel/passkeys` (a transitive dependency of `laravel/fortify` v1.37.2+) registers a global `Route::bind('passkey', ...)` model binding and `DELETE /user/passkeys/{passkey}` via Fortify's route file. Fortify's route takes priority over Keystone's own passkey delete route. Both middlewares return HTTP 423 for unconfirmed JSON requests, so the end-user behavior is identical; the conflict affects route ownership only. This will be resolved when Keystone migrates its passkey backend from `spatie/laravel-passkeys` to `laravel/passkeys` (see planned work below).

### Planned

- Migrate the internal passkey backend from `spatie/laravel-passkeys` to `laravel/passkeys` (first-party Laravel package). This is an internal change only — the public API surface (`BSPDX\Keystone\Models\Passkey`, `BSPDX\Keystone\Contracts\HasPasskeys`, `PasskeyServiceInterface`) will remain unchanged so consuming apps require no updates.

### Removed

- `features.registration` config key — Keystone is presentation-only for registration (Fortify owns the endpoint). The `$showRegisterLink` component prop on `<x-keystone-login-form>` is the correct control surface.
- `features.update_passwords` config key — Keystone does not own the password update endpoint (Fortify does). The profile view now renders the password-change section unconditionally; host apps can suppress it by publishing views.

### Changed

- Login form register-link visibility is now controlled solely by the `$showRegisterLink` component prop, removing the redundant `config('keystone.features.registration')` check
- Profile `show.blade.php` password-change section is now unconditional (no longer gated on the removed `features.update_passwords` flag)

---

## [0.8.1] - 2026-06-07

This ia purely a DX change, so that the user does not have to concern themself with Spatie types when working with the passkey service. The `bspdx/keystone` package wraps `spatie/laravel-passkeys` internally, but previously leaked Spatie types into the public API surface. Any consuming app that type-hints `PasskeyServiceInterface` methods or inspects `KeystoneUser`'s interface chain would see `Spatie\LaravelPasskeys\Models\Passkey` and `Spatie\LaravelPasskeys\Contracts\HasPasskeys`. The goal is to ensure consuming apps only ever reference `BSPDX\Keystone\*` namespaces.

### Changed

- Introduced `BSPDX\Keystone\Models\Passkey` model extending `Spatie\LaravelPasskeys\Models\Passkey`, giving the package a Keystone-owned public type for passkey records
- `PasskeyServiceInterface` method signatures now reference `BSPDX\Keystone\Models\Passkey` instead of `Spatie\LaravelPasskeys\Models\Passkey` — consuming apps no longer need to import any Spatie class when working with the passkey service
- `KeystoneUser` now implements `BSPDX\Keystone\Contracts\HasPasskeys` instead of `Spatie\LaravelPasskeys\Contracts\HasPasskeys` directly
- `KeystoneServiceProvider` now registers `BSPDX\Keystone\Models\Passkey` as the active passkey model via `passkeys.models.passkey`, ensuring Spatie actions instantiate the Keystone-namespaced type at runtime
- `PasskeyConfig::getPasskeyModel()` return type docblock updated to reference `BSPDX\Keystone\Models\Passkey`
- Updated `USER_MODEL.md` documentation to reflect the new passkey model and API changes
- Updated `README.md` to reflect the new passkey model and API changes, and to fix any lingering references to Spatie Permissions package since it was replaced in 0.8.0.

---

## [0.8.0] - 2026-05-25

This update removes the `spatie/laravel-permission` package dependency and replaces it with a custom RBAC implementation built from the ground up for multi-tenancy. The new system provides full control over permission checking logic, better performance, and a simpler mental model while maintaining the same method signatures for backward compatibility.

### Added

**Custom RBAC System** 🎉
- Complete custom role-based access control implementation replacing Spatie Permission
  - Full control over multi-tenant architecture
  - Native Laravel Gate integration
  - Optimized specifically for multi-tenancy from the ground up
  - Simpler mental model - one clear filtering approach
- New `PermissionRegistrar` service for Laravel Gate integration
  - Dynamic permission checking via `Gate::before()` callback
  - Super-admin bypass support
  - Handles permissions created on-the-fly (e.g., in tests)
- Comprehensive multi-tenancy documentation (720+ lines) at [docs/multi-tenancy.md](docs/multi-tenancy.md)
- Timestamps and soft deletes support on all pivot tables
- Relationship caching with automatic cache invalidation

**Multi-Tenancy Enhancement**
- New migration `2024_01_01_00003_add_tenant_id_to_pivot_tables.php` adds `tenant_id` to pivot tables
- `tenant_id` now added to `model_has_roles` and `model_has_permissions` pivot tables when multi-tenancy is enabled
- Automatic backfilling of `tenant_id` in pivot tables from user records
- Global tenant scopes added to `KeystoneRole` and `KeystonePermission` models
- Auto-population of `tenant_id` when creating roles/permissions
- `scopeWithoutTenant()` helper method for super-admin operations
- Global roles/permissions supported via `tenant_id = NULL` (accessible across all tenants)

**Query Scopes**
- Added `scopeRole()` to `HasKeystone` for filtering users by one or more assigned role names

### Changed

**Custom RBAC Implementation**
- Completely rewrote `HasKeystone` trait with custom RBAC methods
  - Custom `roles()` and `permissions()` relationships with tenant filtering
  - Custom `assignRole()`, `removeRole()`, `syncRoles()` methods
  - Custom `givePermissionTo()`, `revokePermissionTo()`, `syncPermissions()` methods
  - Custom `hasRole()`, `hasPermissionTo()`, `getAllPermissions()` methods
  - Maintained same method signatures as Spatie for backward compatibility
  - Removed all Spatie trait dependencies
- Updated `KeystoneRole` and `KeystonePermission` models
  - Now use native Laravel `BelongsToMany` relationships
  - Simplified global scopes that work correctly with relationships
  - Removed Spatie model extensions
  - Added relationship cache invalidation
- Updated middleware to use custom RBAC methods
  - `EnsureHasRole` now uses custom `hasAnyRole()` method
  - `EnsureHasPermission` now uses custom `hasPermissionTo()` method
- Simplified database migrations
  - Removed all `config('permission.*')` references
  - Use only `config('keystone.*')` for configuration
  - Direct table creation without Spatie config parsing

**Primary Key Type Detection**
- `tenant_id` columns **always use UUID** type (to match `users.tenant_id`)
- `model_id` columns still auto-detect from User model (UUID or bigint)
- Fixed UUID detection: now checks if `uniqueIds()` method returns non-empty array
- Migration `2024_01_01_00001_add_keystone_fields_to_users_table` creates UUID `tenant_id`
- Migration `2024_01_01_00002_create_permission_tables` uses improved UUID detection
- Migration `2024_01_01_00004_create_passkeys_table` uses improved UUID detection

**Query Improvements**
- Pivot table queries now use `wherePivotIn('tenant_id', [$this->tenant_id, null])` for cleaner SQL
- Null tenant handling: users without `tenant_id` can access global roles/permissions
- Relationship queries automatically reload after modifications (via `unsetRelation()`)

**Service Provider**
- Gate registration now works correctly in test environment
- Removed `TenantTeamResolver` (Spatie-specific)
- Added `PermissionRegistrar` for Gate integration
- Simplified service bindings

### Fixed

- UUID detection bug: `method_exists($model, 'uniqueIds')` now also checks `count($model->uniqueIds()) > 0`
- Pivot table SQL errors with `orWhereNull()` inside closures
- Relationship caching after `assignRole()` and `givePermissionTo()` calls
- Gate not registered during PHPUnit tests (was blocked by `runningInConsole()` check)
- Null tenant filtering causing users without `tenant_id` to have no access
- Role permission caching in `KeystoneRole::givePermissionTo()` method
- `tenant_id` column type mismatches (now always UUID)
- Removed lingering `Spatie\Permission\PermissionServiceProvider` registration from `bootstrap/providers.php`
- Removed unused `teams` and `team_resolver` configuration options (multi-tenancy now handled via global scopes)
- Updated cache key from `spatie.permission.cache` to `keystone.permission.cache`
- Updated configuration comments to reference Keystone models instead of Spatie contracts
- Removed references to non-existent `TenantTeamResolver` class from configuration files

### Removed

- **BREAKING**: `spatie/laravel-permission` package dependency
- `TenantTeamResolver` class (Spatie-specific integration)
- Spatie Permission configuration from `config/keystone.php`
- All Spatie trait usage from `HasKeystone` trait

### Security

- Custom RBAC implementation provides full control over permission checking logic
- Simplified architecture reduces attack surface compared to external dependency
- All permission checks now flow through single `PermissionRegistrar::registerPermissions()` callback

### Testing

- ✅ All 52 tests passing (140 assertions)
- 39 unit tests covering custom RBAC implementation
- 13 feature tests verifying multi-tenant isolation
- Added relationship caching tests
- Added pivot table tenant filtering tests
- Added null tenant handling tests

### Breaking Changes

**For All Installations**
- **BREAKING**: Removed `spatie/laravel-permission` dependency
  - Run `composer remove spatie/laravel-permission` if upgrading
  - Custom RBAC system uses same database schema (no data migration needed)
  - Method signatures remain compatible (minimal code changes)
- **BREAKING**: `tenant_id` columns now **always UUID** (previously followed User model ID type)
  - Affects new installations only
  - Existing installations: run migrations carefully
- **BREAKING**: Configuration changes in `config/keystone.php`
  - Removed all `permission.*` nested configuration
  - Removed `team_foreign_key`, `teams`, and `team_resolver` config
  - Simplified to Keystone-specific settings only

### Migration Guide

**For Existing Keystone Users (0.7.x → 0.8.0):**

1. **Backup your database** (important!)

2. **Remove Spatie package**:
   ```bash
   composer remove spatie/laravel-permission
   ```

3. **Update Keystone**:
   ```bash
   composer require bspdx/keystone:^0.8
   ```

4. **Run new migrations**:
   ```bash
   php artisan migrate
   ```

5. **Clear caches**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

6. **Test your application**:
   ```bash
   php artisan test
   ```

7. **Update custom code** (if needed):
   - Direct Spatie model usage → Use `KeystoneRole` and `KeystonePermission`
   - `Permission::findByName('name')` → `KeystonePermission::where('name', 'name')->first()`
   - All `$user->hasRole()`, `$user->assignRole()`, etc. work unchanged ✅

**For Non-Multi-Tenant Installations:**
- All multi-tenant features guarded by `keystone.features.multi_tenant` config
- Your installation remains backward compatible
- Custom RBAC system provides same functionality with better performance

**Testing Multi-Tenancy:**
1. Enable multi-tenancy in `.env`:
   ```env
   KEYSTONE_MULTI_TENANT=true
   ```

2. Verify tenant isolation works correctly

3. Test super-admin bypass with `withoutTenant()` scope

**Performance Notes:**
- Custom RBAC is **faster** than Spatie (no external package overhead)
- Simpler queries with one filtering approach (global scopes)
- Easier to add permission caching layer in future

---

## [0.7.2] - 2026-02-01

This minor update includes tenant_id to roles and permissions, allowing for tenant-scoped RBAC. 

### Changed

- The nullable `tenant_id` UUID column is now also added to:
  - `permissions` table for tenant-scoped permissions
  - `roles` table for tenant-scoped roles
- `tenant_id` is nullable: `NULL` means global (accessible across all tenants)
- Unique constraints updated to include `tenant_id` when multi-tenancy is enabled
- Consolidated Spatie Permission configuration into `config/keystone.php` under `permission`
- Permission table migrations now read from `keystone.permission` instead of `permission`
- Permission migration cache flush now skips database cache store when the cache table is missing (avoids SQLite migration failures)
---

## [0.7.1] - 2026-02-01

This patch offers better multi-tenancy support.

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

## [0.4.0]

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

Review the [Migration Guide](MIGRATING-TO-SUTHKIT-0.4.0.md) for help migrating to this new version. 

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
