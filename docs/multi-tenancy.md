# Multi-Tenancy in Keystone

Keystone provides built-in multi-tenant support for roles and permissions using a **custom RBAC (Role-Based Access Control) system**, allowing you to isolate user access control by tenant while maintaining the flexibility to share global roles and permissions across all tenants.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Configuration](#configuration)
- [Database Schema](#database-schema)
- [Global vs Tenant-Specific](#global-vs-tenant-specific)
- [How It Works](#how-it-works)
- [Super-Admin Bypass](#super-admin-bypass)
- [Working with Roles and Permissions](#working-with-roles-and-permissions)
- [Laravel Gate Integration](#laravel-gate-integration)
- [Migration Guide](#migration-guide)
- [Troubleshooting](#troubleshooting)

## Architecture Overview

Keystone's multi-tenant implementation uses **custom role and permission management with Laravel global scopes**. This design provides several advantages:

- **Full control over multi-tenancy** - No dependency on external packages
- **Automatic filtering** - Authenticated users only see roles/permissions for their tenant
- **Support for global resources** - Roles and permissions can be global (NULL tenant_id) or tenant-specific
- **Clean separation** - Tenant logic is handled at the model level through Eloquent global scopes
- **Optimized queries** - Uses `wherePivotIn()` for efficient tenant filtering in relationships

### Custom RBAC vs Spatie Permission

Keystone previously used Spatie's Laravel Permission package but switched to a custom implementation to:

1. **Avoid filtering conflicts** - Spatie's team feature conflicted with Keystone's global scopes
2. **Simplify multi-tenancy** - One clear filtering mechanism instead of two competing approaches
3. **Optimize for multi-tenancy** - Built specifically for multi-tenant scenarios from the ground up
4. **Reduce dependencies** - Full control over the implementation and future enhancements

The API remains similar to Spatie's for easy migration, but the implementation is optimized for multi-tenant use cases.

## Configuration

### Enable Multi-Tenancy

In your `.env` file:

```env
KEYSTONE_MULTI_TENANT=true
```

Or in `config/keystone.php`:

```php
'features' => [
    'multi_tenant' => env('KEYSTONE_MULTI_TENANT', false),
],
```

### User Model Setup

Your User model should use the `HasKeystone` trait and have a `tenant_id` column:

```php
use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasKeystone;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',  // UUID or bigint
    ];
}
```

### UUID vs BigInt Support

Keystone **always uses UUID for `tenant_id` columns** (to match the users.tenant_id column type), but automatically detects whether your User model's ID column uses UUIDs or big integers:

```php
// If your User model has this method, Keystone uses UUID for model_id in pivot tables
class User extends Authenticatable
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['id'];  // Not empty = uses UUIDs
    }
}
```

Otherwise, `model_id` in pivot tables will be `unsignedBigInteger`.

**Important:** `tenant_id` is ALWAYS UUID regardless of your User model's ID type.

## Database Schema

When multi-tenancy is enabled, the following tables include a `tenant_id` column:

### Users Table

```sql
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  -- or UUID
    tenant_id UUID NULL,  -- ALWAYS UUID
    email VARCHAR(255),
    password VARCHAR(255),
    -- ... other columns
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_tenant_id (tenant_id)
);
```

### Roles Table

```sql
CREATE TABLE roles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id UUID NULL,  -- NULL = global role, accessible to all tenants
    name VARCHAR(255),
    guard_name VARCHAR(255) DEFAULT 'web',
    title VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_role_per_tenant (tenant_id, name, guard_name),
    INDEX idx_tenant_id (tenant_id)
);
```

### Permissions Table

```sql
CREATE TABLE permissions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id UUID NULL,  -- NULL = global permission, accessible to all tenants
    name VARCHAR(255),
    guard_name VARCHAR(255) DEFAULT 'web',
    title VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_permission_per_tenant (tenant_id, name, guard_name),
    INDEX idx_tenant_id (tenant_id)
);
```

### Pivot Tables

```sql
CREATE TABLE model_has_roles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT,
    model_type VARCHAR(255),
    model_id BIGINT,  -- or UUID based on User model
    tenant_id UUID NULL,  -- ALWAYS UUID, tracks which tenant the assignment belongs to
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,  -- Soft deletes for audit trail
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (tenant_id, role_id, model_id, model_type),
    INDEX idx_tenant_id (tenant_id)
);

CREATE TABLE model_has_permissions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    permission_id BIGINT,
    model_type VARCHAR(255),
    model_id BIGINT,  -- or UUID based on User model
    tenant_id UUID NULL,  -- ALWAYS UUID
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,  -- Soft deletes for audit trail
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (tenant_id, permission_id, model_id, model_type),
    INDEX idx_tenant_id (tenant_id)
);

CREATE TABLE role_has_permissions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    permission_id BIGINT,
    role_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,  -- Soft deletes for audit trail
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (permission_id, role_id)
);
```

**Note:** All pivot tables include `timestamps` and `soft deletes` for comprehensive audit trails.

## Global vs Tenant-Specific

### Global Roles and Permissions

Global roles and permissions have `tenant_id = NULL` and are accessible to users in **all tenants**.

**Use cases:**
- Super Administrator role that exists across all tenants
- System-level permissions like "manage billing" or "access support"
- Shared functionality that shouldn't be duplicated per tenant

**Example:**

```php
use BSPDX\Keystone\Models\KeystoneRole;

// Create a global role (accessible to all tenants)
$superAdmin = KeystoneRole::withoutTenant()->create([
    'name' => 'super-administrator',
    'title' => 'Super Administrator',
    'description' => 'Global administrator with access to all tenants',
    'tenant_id' => null,  // Explicitly set to NULL for global role
]);
```

### Tenant-Specific Roles and Permissions

Tenant-specific roles and permissions have a `tenant_id` value and are **only accessible to users within that tenant**.

**Use cases:**
- Custom roles specific to an organization (e.g., "Department Manager" for Company A)
- Tenant-specific permissions based on their subscription level
- Isolated role structures between different customers

**Example:**

```php
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Auth;

// When authenticated, tenant_id is auto-populated
Auth::login($userInTenantA);

$manager = KeystoneRole::create([
    'name' => 'department-manager',
    'title' => 'Department Manager',
    'description' => 'Manages departments within this organization',
    // tenant_id is automatically set from auth()->user()->tenant_id
]);
```

## How It Works

### Global Scopes

Both `KeystoneRole` and `KeystonePermission` models use a global scope that automatically filters queries by the authenticated user's `tenant_id`:

```php
// In KeystoneRole and KeystonePermission models
protected static function booted(): void
{
    parent::booted();

    static::addGlobalScope('tenant', function (Builder $query) {
        if (config('keystone.features.multi_tenant', false) && auth()->check() && auth()->user()->tenant_id) {
            $tableName = $query->getModel()->getTable();
            $query->where(function ($q) use ($tableName) {
                $q->where("{$tableName}.tenant_id", auth()->user()->tenant_id)
                  ->orWhereNull("{$tableName}.tenant_id");  // Include global roles/permissions
            });
        }
    });
}
```

This means:
- When you query `KeystoneRole::all()`, you only get roles for the current user's tenant + global roles
- You don't need to manually add `where('tenant_id', ...)` to every query
- The filtering happens automatically at the model level

### Auto-Population on Creation

When creating roles or permissions, the `tenant_id` is automatically populated from the authenticated user:

```php
// In KeystoneRole and KeystonePermission models
static::creating(function ($model) {
    if (config('keystone.features.multi_tenant', false) && auth()->check() && !isset($model->tenant_id)) {
        $model->tenant_id = auth()->user()->tenant_id;
    }

    // Also auto-set guard_name if not provided
    if (!isset($model->guard_name)) {
        $model->guard_name = 'web';
    }
});
```

### Relationship Filtering

The `HasKeystone` trait defines tenant-aware relationships for roles and permissions:

```php
// In HasKeystone trait
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
        // If user has no tenant (tenant_id = NULL), show all roles
    }

    return $relation;
}
```

This ensures that:
- Users only see role/permission assignments for their tenant
- Global assignments (tenant_id = NULL) are visible to all tenants
- Users without tenants can access all assignments

## Super-Admin Bypass

Super-administrators may need to view or manage roles/permissions across **all tenants**. Keystone provides the `withoutTenant()` scope for this:

```php
use BSPDX\Keystone\Models\KeystoneRole;

// Get ALL roles across all tenants (bypassing the global scope)
$allRoles = KeystoneRole::withoutTenant()->get();

// Create a global role (super-admin operation)
$globalRole = KeystoneRole::withoutTenant()->create([
    'name' => 'super-admin',
    'tenant_id' => null,
]);

// Find a role in a specific tenant
$roleInTenantB = KeystoneRole::withoutTenant()
    ->where('tenant_id', $tenantBId)
    ->where('name', 'manager')
    ->first();
```

### Authorization Check

You can check if a user is a super-admin:

```php
if ($user->isSuperAdmin()) {
    // User has the super-admin role (bypasses permission checks)
    $allRoles = KeystoneRole::withoutTenant()->get();
} else {
    // User sees only their tenant's roles
    $tenantRoles = KeystoneRole::all();
}

// Or use the method directly
if ($user->canBypassPermissions()) {
    // Same as isSuperAdmin()
}
```

## Working with Roles and Permissions

### Assigning Roles

```php
// Assign a role to a user
$user->assignRole('manager');

// Assign multiple roles
$user->assignRole('manager', 'editor');

// Assign using role model
$role = KeystoneRole::where('name', 'manager')->first();
$user->assignRole($role);

// Remove a role
$user->removeRole('manager');

// Sync roles (replace all existing roles with new set)
$user->syncRoles('manager', 'editor');
```

### Assigning Permissions

```php
// Give permission directly to a user
$user->givePermissionTo('edit-posts');

// Give multiple permissions
$user->givePermissionTo('edit-posts', 'delete-posts');

// Revoke a permission
$user->revokePermissionTo('delete-posts');

// Sync permissions
$user->syncPermissions('edit-posts', 'publish-posts');
```

### Checking Roles and Permissions

```php
// Check if user has a role
if ($user->hasRole('manager')) {
    // User has the manager role
}

// Check multiple roles (any)
if ($user->hasAnyRole('manager', 'admin')) {
    // User has at least one of these roles
}

// Check all roles
if ($user->hasAllRoles('manager', 'editor')) {
    // User has ALL of these roles
}

// Check permissions
if ($user->hasPermissionTo('edit-posts')) {
    // User has this permission (either directly or via role)
}

// Check if user has permission directly (not via role)
if ($user->hasDirectPermission('edit-posts')) {
    // User was given this permission directly
}

// Get all permissions (direct + via roles)
$permissions = $user->getAllPermissions();
```

### Role Permissions

```php
use BSPDX\Keystone\Models\KeystoneRole;

$role = KeystoneRole::where('name', 'manager')->first();

// Assign permissions to a role
$role->givePermissionTo('edit-posts', 'delete-posts');

// Check if role has permission
if ($role->hasPermissionTo('edit-posts')) {
    // Role has this permission
}

// Get all permissions for a role
$permissions = $role->permissions;
```

## Laravel Gate Integration

Keystone automatically registers all permissions with Laravel's Gate system, allowing you to use standard Laravel authorization:

```php
// In controllers
if ($user->can('edit-posts')) {
    // User has permission
}

// Using Gate facade
use Illuminate\Support\Facades\Gate;

if (Gate::allows('edit-posts')) {
    // Current user has permission
}

// In Blade templates
@can('edit-posts')
    <button>Edit Post</button>
@endcan

// Authorize in controllers
public function edit(Post $post)
{
    $this->authorize('edit-posts');
    // ...
}
```

### How Gate Integration Works

The `PermissionRegistrar` service registers a `Gate::before` callback that:

1. Checks if user is a super-admin (bypasses all permission checks)
2. Looks up the permission by name
3. Checks if the user has that permission via `hasPermissionTo()`

```php
// In PermissionRegistrar
$gate->before(function ($user, $ability) {
    // Super-admin bypass
    if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
        return true;
    }

    // Check if ability corresponds to a permission
    if (method_exists($user, 'hasPermissionTo')) {
        $permission = KeystonePermission::withoutTenant()
            ->where('name', $ability)
            ->first();

        if ($permission) {
            return $user->hasPermissionTo($permission) ? true : false;
        }
    }

    return null;  // Let Laravel continue checking policies
});
```

## Migration Guide

### Migrating from Non-Multi-Tenant Setup

If you have an existing Keystone installation without multi-tenancy:

#### Step 1: Backup Your Database

```bash
php artisan db:backup
# or use your database backup tool
```

#### Step 2: Update Configuration

Enable multi-tenancy in your `.env`:

```env
KEYSTONE_MULTI_TENANT=true
```

#### Step 3: Add tenant_id to Users

If your users table doesn't have a tenant_id column:

```bash
php artisan make:migration add_tenant_id_to_users_table
```

```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->uuid('tenant_id')->nullable()->after('id');
        $table->index('tenant_id');
    });
}
```

#### Step 4: Run Migrations

```bash
php artisan migrate
```

The migration will:
- Add `tenant_id` to roles and permissions tables
- Add `tenant_id` to pivot tables
- Add timestamps and soft deletes to pivot tables

#### Step 5: Assign Users to Tenants

```php
use App\Models\User;

// Assign existing users to default tenant
$defaultTenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';

User::whereNull('tenant_id')->update([
    'tenant_id' => $defaultTenantId
]);
```

#### Step 6: Update Existing Roles/Permissions

Decide which roles and permissions should be global vs tenant-specific:

```php
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\DB;

// Make certain roles global
DB::table('roles')
    ->whereIn('name', ['super-administrator', 'support-agent'])
    ->update(['tenant_id' => null]);

// Assign tenant_id to tenant-specific roles
DB::table('roles')
    ->whereNotIn('name', ['super-administrator', 'support-agent'])
    ->update(['tenant_id' => $defaultTenantId]);
```

#### Step 7: Test Tenant Isolation

```php
// User A in Tenant 1
Auth::login($userATenant1);
$roles = KeystoneRole::all();  // Should only see Tenant 1 roles + global roles

// User B in Tenant 2
Auth::login($userBTenant2);
$roles = KeystoneRole::all();  // Should only see Tenant 2 roles + global roles

// Verify isolation
$this->assertNotContains($tenantSpecificRoleForTenant1, $tenant2Roles);
```

### Migrating from Spatie Permission

Keystone now uses a custom RBAC implementation instead of Spatie's package. The API is similar, but some differences exist:

#### Key Differences

1. **No Spatie dependency** - Keystone manages roles and permissions internally
2. **Custom relationships** - Uses `wherePivotIn()` for tenant filtering
3. **Always use Keystone models** - Use `KeystoneRole` and `KeystonePermission`, not Spatie's models

#### Migration Steps

1. **Your code should mostly work** - The API (assign Role, hasPermissionTo, etc.) is the same
2. **Remove direct Spatie calls** - Don't use Spatie's `Permission::findByName()` directly
3. **Use Keystone models** - Import `use BSPDX\Keystone\Models\{KeystoneRole, KeystonePermission}`

## Troubleshooting

### Roles/Permissions Not Showing Up

**Issue:** After enabling multi-tenancy, roles or permissions don't appear.

**Solution:** Ensure the authenticated user has a `tenant_id`:

```php
dd(auth()->user()->tenant_id);  // Should not be NULL (or should be NULL if user is global)
```

If users without tenants should see everything, this is expected behavior. Otherwise, assign users to tenants:

```php
$user->update(['tenant_id' => $tenant->id]);
```

### Can't Access Global Roles

**Issue:** Global roles (tenant_id = NULL) aren't accessible to users.

**Solution:** Verify the global scope includes `orWhereNull('tenant_id')` - this is built into Keystone's models and should work automatically.

### Need to Bypass Tenant Filtering

**Issue:** Super-admin needs to see all roles across all tenants.

**Solution:** Use the `withoutTenant()` scope:

```php
$allRoles = KeystoneRole::withoutTenant()->get();
```

Or check if the user is a super-admin:

```php
if ($user->isSuperAdmin()) {
    // Perform super-admin operations
}
```

### Pivot Table tenant_id is NULL

**Issue:** After assigning roles, the pivot table's `tenant_id` is NULL when it shouldn't be.

**Solution:** Verify that the user being assigned has a `tenant_id`. The pivot table copies the user's `tenant_id`:

```php
// When assigning
$user->assignRole('manager');

// The pivot record will have:
// tenant_id = $user->tenant_id
```

### Permission Checks Always Fail

**Issue:** `$user->can('permission-name')` always returns false.

**Solution:** Ensure:
1. The permission exists in the database
2. The user has the permission (directly or via role)
3. The PermissionRegistrar is registered in your service provider

The registration happens automatically in `KeystoneServiceProvider::boot()`.

### Role Assignment Not Persisting

**Issue:** After calling `assignRole()`, the role isn't in `$user->roles`.

**Solution:** Relationships are cached. The `assignRole()` method should call `unsetRelation('roles')` to force a reload. This is built into Keystone and should work automatically.

If you're loading roles before assignment, reload them:

```php
$user->assignRole('manager');
$user->load('roles');  // Force reload
```

## Further Reading

- [Multi-Tenant Usage Examples](examples/multi-tenant-usage.md)
- [Keystone User Model Documentation](USER_MODEL.md)
- [Laravel Authorization Documentation](https://laravel.com/docs/authorization)
