# Design: Remove authentication, keep multi-tenant RBAC

**Date:** 2026-08-17
**Status:** Approved by user, ready for implementation plan

## Context

`AGENTS.md` ("Strategic Direction") documents a recommended long-term pivot: Laravel's
first-party `laravel/passkeys` package now integrates with Fortify behind
`Features::passkeys()`, and combined with Fortify's existing TOTP support, Fortify is on a
path to owning the full auth ceremony stack (password, TOTP, passkeys, passwordless login).
Keystone's own auth orchestration layer (controllers, service interfaces, passkey/2FA
plumbing) becomes redundant overlap. What Laravel has no first-party answer for — and what
Keystone should narrow down to — is **multi-tenant RBAC**: the `HasKeystone` trait,
`Gate::before()` integration, and role/permission enforcement.

The user asked to execute this pivot now, not wait for `laravel/passkeys` to hit v1.0. This
spec covers removing all authentication orchestration (Fortify integration, Sanctum, passkeys,
TOTP 2FA, password confirmation, account deletion, all Blade UI) and keeping only the
role/permission/tenancy layer.

## Decisions (confirmed with user)

1. **User model ownership: trait-only, no owned table.** Keystone drops
   `Models/KeystoneUser.php` and the `create_keystone_users_table` migration entirely. It
   ships only the `HasKeystone` trait plus role/permission/pivot tables (with `tenant_id`).
   The host app's own User model (authenticated however the host app chooses — typically
   Fortify) adds the trait to gain roles, permissions, and tenant scoping.
2. **Blade UI: removed entirely.** All of `resources/views/`, all of `src/View/Components/*`,
   and the `resources/css`/`resources/js` scaffolding (which existed only to support the auth
   UI) are deleted. Keystone becomes backend-only: models, services, middleware, Gate
   integration, Artisan commands, and an optional RBAC API route file.
3. **Versioning:** same package (`bspdx/keystone`), same repo, work happens directly on
   `develop`. This is a breaking change — next version is **0.10.0**, marked **BREAKING** in
   CHANGELOG.md with a **Breaking Changes** section and a step-by-step **Migration Guide**,
   per this repo's own CHANGELOG conventions (pre-1.0 breaking changes get a new `0.X.0`).
4. **Single commit.** The entire removal (code, config, migrations, tests, docs) lands as one
   commit, not an incremental series.

## Dependencies removed

From `composer.json` `require`: `laravel/fortify`, `laravel/sanctum`,
`spatie/laravel-passkeys`, `pragmarx/google2fa-laravel`. Nothing in the surviving code
references these once `HasKeystone` is stripped (see below) — verified by grep across `src/`.

`composer.json` `description` and `keywords` get rewritten to lead with RBAC/multi-tenancy and
drop Fortify/Passkeys/2FA/WebAuthn branding. The `replace: bspdx/authkit` entry is historical
and untouched.

## `HasKeystone` trait (the core surviving piece)

Remove the trait uses of `HasApiTokens` (Sanctum), `TwoFactorAuthenticatable` (Fortify), and
`InteractsWithPasskeys` (Spatie). Remove these methods entirely:
`twoFactorQrCodeSvg`, `hasTwoFactorEnabled`, `requires2FA`, `hasPasskeysRegistered`,
`requiresPasskey`, `canUsePasswordlessLogin`, `getAuthenticationMethods`,
`getAvailableAuthMethods`, `hasValidAuthConfiguration`, `getAuthPreferenceFillable`,
`getAuthPreferenceCasts`.

Keep unchanged: `scopeRole`, `roles()`/`permissions()` relationships (with tenant-pivot
filtering), `assignRole`/`removeRole`/`syncRoles`, `givePermissionTo`/`revokePermissionTo`/
`syncPermissions`, `hasRole`/`hasAnyRole`/`hasAllRoles`, `hasPermissionTo`/
`hasPermissionViaRole`, `getAllPermissions`, `hasAnyPermission`/`hasAllPermissions`,
`hasDirectPermission`, `convertToRoleModels`/`convertToPermissionModels`,
`forgetCachedPermissions`, `isSuperAdmin`/`canBypassPermissions`.

## Files removed entirely

**Models/contracts:**
- `src/Models/KeystoneUser.php`
- `src/Models/Passkey.php`
- `src/Contracts/HasPasskeys.php`

**Controllers:**
- `src/Http/Controllers/LoginController.php`
- `src/Http/Controllers/PasskeyAuthController.php`
- `src/Http/Controllers/TwoFactorAuthController.php`
- `src/Http/Controllers/AccountDeletionController.php`
- `src/Http/Controllers/ProfileController.php`
- `src/Http/Controllers/Concerns/ThrottlesAuthentication.php`

**Middleware:**
- `src/Http/Middleware/RequirePasswordConfirm.php`
- `src/Http/Middleware/EnsureTwoFactorEnabled.php`
- `src/Http/Middleware/RequirePasskey2FA.php`

**Services/support:**
- `src/Services/Contracts/PasskeyServiceInterface.php`
- `src/Services/PasskeyService.php`
- `src/Support/PasskeyConfig.php`
- `src/Actions/GeneratePasskeyRegisterOptionsAction.php`

**Console commands:**
- `src/Console/Commands/MakeUserCommand.php`
- `src/Console/Commands/ChangePasswordCommand.php`

**Blade UI (all of it):**
- `resources/views/**` (splash, profile, two-factor-challenge, passkeys/2fa-challenge, all
  `components/*` including `profile/*`)
- `src/View/Components/*.php` (LoginForm, RegisterForm, PasskeyLogin, PasskeyRegister,
  TwoFactorChallenge)
- `resources/css/app.css`, `resources/js/app.js`, `resources/js/bootstrap.js`

**Migrations (package + test mirrors under `tests/database/migrations`):**
- `2024_01_01_00000_create_keystone_users_table.php`
- `2024_01_01_00004_create_passkeys_table.php`
- `2024_01_01_00005_add_auth_preferences_to_users_table.php`
- `tests/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`
  (Sanctum)

**Other docs:**
- `PASSKEY-AUTHENTICATABLE-CONFIG-BUG.md` (untracked, never committed — describes a bug in
  code being deleted; moot)

## Files kept as-is

- `src/Http/Controllers/RolePermissionController.php`
- `src/Http/Middleware/EnsureHasRole.php`, `EnsureHasPermission.php`, `EnsureFeatureEnabled.php`
- `src/Models/KeystoneRole.php`, `src/Models/KeystonePermission.php`
- `src/Services/{RoleService,PermissionService,AuthorizationService,PermissionRegistrar,CacheService}.php`
  and their `Contracts/*Interface.php`
- `src/Console/Commands/{AssignRoleCommand,AssignPermissionCommand,UnassignRoleCommand,UnassignPermissionCommand,MakeRoleCommand,MakePermissionCommand}.php`
- `src/Console/Commands/Concerns/InteractsWithKeystone.php` (unchanged — already
  auth-agnostic, resolves the user model via `keystone.user.model` config or
  `auth.providers.users.model`)
- `database/factories/UserFactory.php`, `database/seeders/KeystoneSeeder.php` — both already
  resolve the user model generically (`config('keystone.user.model') ?? config('auth.providers.users.model', ...)`)
  and contain no auth-specific fields; no changes needed

## Files edited (not deleted)

- **`database/migrations/2024_01_01_00002_create_permission_tables.php`** and
  **`database/migrations/2024_01_01_00003_add_tenant_id_to_pivot_tables.php`** (plus their
  `tests/database/migrations` mirrors): both call `PasskeyConfig::getAuthenticatableModel()` to
  detect whether the user model uses UUIDs. Since `PasskeyConfig` is deleted, replace that call
  with the same resolution pattern used elsewhere:
  `config('keystone.user.model') ?? config('auth.providers.users.model', \App\Models\User::class)`,
  and drop the `use BSPDX\Keystone\Support\PasskeyConfig;` import.

- **`src/KeystoneServiceProvider.php`**: remove the `passkeys.*` config bridging block, the
  `GeneratePasskeyRegisterOptionsAction` binding, and `PasskeyServiceInterface`/`PasskeyService`
  singleton + alias registrations, and any `Laravel\Fortify\*` imports/usages
  (`TwoFactorChallengeViewResponse`, `Fortify` facade). Keep all RBAC service bindings/aliases
  and `PermissionRegistrar` registration untouched.
- **`database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php`** (and its
  `tests/database/migrations` mirror): currently adds both Fortify 2FA columns
  (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) and `tenant_id`.
  Strip to just the `tenant_id` column/index logic (both `up()` and `down()`). While here, drop
  the `config('keystone.user.primary_key_type') === 'uuid'` branch that chose between
  `uuid('tenant_id')` and `unsignedBigInteger('tenant_id')` — `create_permission_tables.php`
  already always uses `uuid('tenant_id')` regardless of the user model's own key type (per
  CLAUDE.md: "`tenant_id` is always UUID"), so make this migration consistent with that and
  always emit `uuid('tenant_id')`.
- **`app/Models/User.php`** (the repo's own dev/test stand-in for a host app's User model):
  remove `implements HasPasskeys` and the `use BSPDX\Keystone\Contracts\HasPasskeys;` import —
  it already only uses `HasKeystone`, nothing else changes.
- **`bootstrap/providers.php`**: remove the
  `Spatie\LaravelPasskeys\LaravelPasskeysServiceProvider::class` line.
- **`routes/web.php`**: remove splash page, profile routes, account deletion, passwordless
  login, two-factor, and passkey route groups. Keep only the example RBAC-protected route
  groups (`role:admin`, `permission:edit-posts`).
- **`routes/api.php`**: remove the Two-Factor Authentication API block and the Passkey API
  block. Keep the Role & Permission Management API block. Replace the hardcoded
  `auth:sanctum` middleware with a code comment documenting that the host app should apply
  whatever auth guard it uses — Keystone no longer assumes Sanctum.

## Config (`config/keystone.php`)

**Remove:** `passkey.*` (entire section), `two_factor.*` (entire section), `redirects.*`,
`rate_limiting.*`, `session.*`, `profile.*`, and these feature flags:
`features.two_factor`, `features.passkeys`, `features.passkey_2fa`,
`features.account_deletion`, `features.passwordless_login`.

**Keep:** `load_routes`, `rbac.*` (`cache_expiration`, `default_role`, `super_admin_role`),
`features.multi_tenant`, `user.model` (still consumed by `InteractsWithKeystone::getUserModel()`).

**Also remove:** `features.show_permissions` — its only consumer, `ProfileController::show()`,
is deleted along with the rest of the Blade/profile UI, so the flag has nothing left to gate.

**Remove from `user.*`:** `primary_key_type`, `table_name` — both only meaningful for the
now-deleted owned `KeystoneUser`/its table.

## Tests

**Delete:** `tests/Feature/AccountDeletionTest.php`, `PasskeyConfigTest.php`,
`PasskeyTwoFactorTest.php`, `RateLimitingTest.php`, `RequirePasswordConfirmTest.php`,
`TwoFactorConfigTest.php`. Also delete `tests/Feature/ConfigRegressionTest.php` in full — every
test in it covers a deleted config key/feature (passkey options, 2FA recovery codes,
`redirects.login`, feature-flag 404 guards, `keystone:make-user`) and none cover surviving
RBAC/multi-tenant config. Also delete `tests/Feature/ShowPermissionsTest.php` in full — every
test in it drives `ProfileController::show()`, which is deleted.

**Prune (not delete):** `tests/Feature/KeystoneTest.php` — remove the three auth-flavored tests
(`user_can_check_two_factor_status`, `user_can_check_if_two_factor_is_required_for_role`,
`user_can_get_authentication_methods`), keep the rest. `tests/Unit/HasKeystoneTraitTest.php` —
remove the six auth-flavored tests (two-factor status/required, passkey registered/required,
`getAuthenticationMethods`), keep `it_can_identify_super_admin` and
`it_can_check_if_user_can_bypass_permissions`.

**Keep as-is:** `tests/Unit/Models/MultiTenant{Permission,Role}Test.php`,
`tests/Unit/Traits/MultiTenant{Permission,Role}AssignmentTest.php`,
`tests/Unit/Services/PermissionRegistrarTest.php`, `tests/Feature/ExampleTest.php`,
`tests/Unit/ExampleTest.php`.

**Update:** `tests/TestCase.php` and `tests/database/migrations/*` — build the test app's
`users` table as a plain table (no `KeystoneUser`-specific columns) and apply `HasKeystone` to
a plain test User model, matching how a real host app would use the package.

## Docs

- **README.md**: rewrite feature list, Architecture section (Service Layer/Models), and
  Requirements to drop Fortify/Sanctum/Passkeys/2FA and lead with multi-tenant RBAC. Remove
  documented auth routes/config/UI usage examples.
- **`docs/USER_MODEL.md`**: rewrite around "add `HasKeystone` to your own User model" instead
  of describing `KeystoneUser`.
- **`docs/multi-tenancy.md`**, **`docs/examples/multi-tenant-usage.md`**: reviewed for any
  references to removed auth features; otherwise unchanged, this is the surviving core
  functionality.
- **`CHANGELOG.md`**: new `## [0.10.0] - 2026-08-17` entry at the top with **BREAKING**-marked
  Removed/Changed sections and a **Migration Guide** (host apps must: keep their own Fortify
  installation for auth, add `HasKeystone` to their own User model instead of extending
  `KeystoneUser`, run the trimmed migrations, remove any published Keystone auth routes/views,
  update `config/keystone.php` to the new shape).
- **`AGENTS.md`**: mark the "Strategic Direction" section as done (this pivot is now
  implemented) rather than a future recommendation; keep or trim "Historical Notes" as
  appropriate.
- **`.claude/.copilot-instructions.md`**: check for stale auth-package framing, update if
  present.

## Out of scope

- No new `Tenant` model or tenant-management UI — unchanged, Keystone has never owned tenant
  creation.
- No change to the RBAC data model itself (`KeystoneRole`, `KeystonePermission`, pivot table
  shapes) beyond what's already listed above.
- No package rename or split into a separate repo.
