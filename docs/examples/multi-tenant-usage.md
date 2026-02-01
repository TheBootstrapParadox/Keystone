# Multi-Tenant Usage Examples

This guide provides practical examples for working with Keystone's multi-tenant role and permission system.

## Table of Contents

- [Setup](#setup)
- [Creating Global Roles](#creating-global-roles)
- [Creating Tenant-Specific Roles](#creating-tenant-specific-roles)
- [Assigning Roles](#assigning-roles)
- [Permission Checking](#permission-checking)
- [Super-Admin Operations](#super-admin-operations)
- [Common Patterns](#common-patterns)
- [Best Practices](#best-practices)

## Setup

Ensure multi-tenancy is enabled in your configuration:

```php
// config/keystone.php
'features' => [
    'multi_tenant' => true,
],
```

## Creating Global Roles

Global roles are accessible to users in **all tenants**. These are perfect for system-level roles like "Super Administrator" or "Support Agent".

### Example 1: Creating a Super Administrator Role

```php
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;

// Create global permissions first
$manageSystemPermission = KeystonePermission::withoutTenant()->create([
    'name' => 'manage-system',
    'title' => 'Manage System',
    'description' => 'Full system management access',
    'tenant_id' => null,  // Explicitly set to NULL for global permission
]);

$manageBillingPermission = KeystonePermission::withoutTenant()->create([
    'name' => 'manage-billing',
    'title' => 'Manage Billing',
    'description' => 'Access to billing and subscription management',
    'tenant_id' => null,
]);

// Create global super administrator role
$superAdmin = KeystoneRole::withoutTenant()->create([
    'name' => 'super_administrator',
    'title' => 'Super Administrator',
    'description' => 'Global administrator with access to all tenants and system features',
    'tenant_id' => null,  // Global role
]);

// Attach global permissions
$superAdmin->givePermissionTo([
    $manageSystemPermission,
    $manageBillingPermission,
]);
```

### Example 2: Creating a Support Agent Role

```php
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;

// Global permission for viewing all tickets
$viewAllTickets = KeystonePermission::withoutTenant()->create([
    'name' => 'view-all-tickets',
    'title' => 'View All Support Tickets',
    'description' => 'Can view support tickets across all tenants',
    'tenant_id' => null,
]);

// Global support agent role
$supportAgent = KeystoneRole::withoutTenant()->create([
    'name' => 'support_agent',
    'title' => 'Support Agent',
    'description' => 'Customer support representative',
    'tenant_id' => null,
]);

$supportAgent->givePermissionTo($viewAllTickets);
```

## Creating Tenant-Specific Roles

Tenant-specific roles are isolated to a single organization or customer.

### Example 3: Creating Department Manager Role

```php
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Support\Facades\Auth;

// Assume user is authenticated and belongs to a tenant
// The tenant_id will be auto-populated from auth()->user()->tenant_id

// Create tenant-specific permissions
$manageEmployees = KeystonePermission::create([
    'name' => 'manage-employees',
    'title' => 'Manage Employees',
    'description' => 'Can add, edit, and remove employees',
    // tenant_id is automatically set from authenticated user
]);

$approveTimeOff = KeystonePermission::create([
    'name' => 'approve-time-off',
    'title' => 'Approve Time Off Requests',
    'description' => 'Can approve or deny employee time-off requests',
]);

// Create tenant-specific role
$manager = KeystoneRole::create([
    'name' => 'department_manager',
    'title' => 'Department Manager',
    'description' => 'Manages a department within the organization',
    // tenant_id auto-populated
]);

$manager->givePermissionTo([
    $manageEmployees,
    $approveTimeOff,
]);
```

### Example 4: Creating Role for Specific Tenant (Programmatic)

```php
use BSPDX\Keystone\Models\KeystoneRole;
use App\Models\Tenant;

$tenant = Tenant::where('name', 'ACME Corporation')->first();

// Temporarily authenticate as a user from that tenant to set context
$userFromTenant = $tenant->users()->first();
Auth::login($userFromTenant);

// Now create the role - tenant_id will be auto-populated
$customRole = KeystoneRole::create([
    'name' => 'acme_custom_role',
    'title' => 'ACME Custom Role',
    'description' => 'Special role for ACME Corporation',
]);

// Or explicitly set tenant_id using withoutTenant() to bypass auto-population
$explicitRole = KeystoneRole::withoutTenant()->create([
    'name' => 'explicit_role',
    'title' => 'Explicit Tenant Role',
    'description' => 'Role with explicitly set tenant',
    'tenant_id' => $tenant->id,
]);
```

## Assigning Roles

### Example 5: Assigning Role to User

```php
use App\Models\User;

// Get user
$user = User::find($userId);

// Assign tenant-specific role (user must be authenticated or have tenant_id set)
$user->assignRole('department_manager');

// Assign global role
$user->assignRole('super_administrator');

// Assign multiple roles
$user->assignRole(['department_manager', 'hr_representative']);
```

### Example 6: Assigning Role Across Tenants (Programmatic)

```php
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\DB;

// Scenario: Assign same role name to users in different tenants
$tenantAUsers = User::where('tenant_id', $tenantAId)->get();
$tenantBUsers = User::where('tenant_id', $tenantBId)->get();

// Authenticate as user from Tenant A
Auth::login($tenantAUsers->first());

// Get or create "manager" role for Tenant A (auto-scoped by global scope)
$managerRoleA = KeystoneRole::firstOrCreate(['name' => 'manager'], [
    'title' => 'Manager',
    'description' => 'Organization manager',
]);

// Assign to all Tenant A users
foreach ($tenantAUsers as $user) {
    $user->assignRole($managerRoleA);
}

// Switch to Tenant B
Auth::login($tenantBUsers->first());

// Get or create "manager" role for Tenant B (different role instance!)
$managerRoleB = KeystoneRole::firstOrCreate(['name' => 'manager'], [
    'title' => 'Manager',
    'description' => 'Organization manager',
]);

// Assign to all Tenant B users
foreach ($tenantBUsers as $user) {
    $user->assignRole($managerRoleB);
}

// Result: Two separate "manager" roles exist with different tenant_ids
```

## Permission Checking

### Example 7: Checking Permissions in Controllers

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use BSPDX\Keystone\Facades\Keystone;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Method 1: Using Keystone facade
        if (!Keystone::userHasAnyPermission($request->user(), 'view-employees')) {
            abort(403, 'Unauthorized');
        }

        // Method 2: Using user model directly
        if (!$request->user()->hasPermissionTo('view-employees')) {
            abort(403);
        }

        // Method 3: Using middleware (recommended)
        // In routes/web.php: Route::get('/employees', ...)->middleware('permission:view-employees');

        return view('employees.index');
    }

    public function store(Request $request)
    {
        // Check multiple permissions
        if (!Keystone::userHasAllPermissions($request->user(), ['create-employees', 'manage-payroll'])) {
            abort(403, 'You need both create-employees and manage-payroll permissions');
        }

        // ... create employee
    }
}
```

### Example 8: Checking Roles

```php
use BSPDX\Keystone\Facades\Keystone;

// Check if user has any of the roles
if (Keystone::userHasAnyRole($user, ['department_manager', 'hr_representative'])) {
    // User is either a manager or HR rep
}

// Check if user has all roles
if (Keystone::userHasAllRoles($user, ['department_manager', 'security_officer'])) {
    // User has both roles
}

// Using user model directly
if ($user->hasRole('super_administrator')) {
    // User is super admin
}

// Check for global vs tenant-specific role
$role = KeystoneRole::where('name', 'admin')->first();
if ($role->tenant_id === null) {
    // This is a global admin role
} else {
    // This is a tenant-specific admin role
}
```

### Example 9: Blade Directives

```blade
@role('department_manager')
    <a href="{{ route('employees.create') }}">Add Employee</a>
@endrole

@hasanyrole('department_manager|hr_representative')
    <a href="{{ route('payroll.index') }}">Manage Payroll</a>
@endhasanyrole

@can('approve-time-off')
    <button>Approve Request</button>
@endcan

@canany(['create-employees', 'edit-employees'])
    <a href="{{ route('employees.manage') }}">Manage Employees</a>
@endcanany
```

## Super-Admin Operations

### Example 10: Viewing All Roles Across Tenants

```php
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Facades\Keystone;

// Check if user can bypass tenant filtering
if (!Keystone::canBypassPermissions($user)) {
    abort(403, 'Super-admin access required');
}

// Get ALL roles across all tenants
$allRoles = KeystoneRole::withoutTenant()->get();

// Group by tenant for display
$rolesByTenant = $allRoles->groupBy('tenant_id');

foreach ($rolesByTenant as $tenantId => $roles) {
    if ($tenantId === null) {
        echo "Global Roles:\n";
    } else {
        $tenant = Tenant::find($tenantId);
        echo "Roles for {$tenant->name}:\n";
    }

    foreach ($roles as $role) {
        echo "  - {$role->title} ({$role->name})\n";
    }
}
```

### Example 11: Managing Roles for Specific Tenant as Super-Admin

```php
use BSPDX\Keystone\Models\KeystoneRole;
use App\Models\Tenant;

$tenant = Tenant::where('name', 'Widget Inc')->first();

// Create a role for a specific tenant without authenticating as that tenant
$roleForWidgetInc = KeystoneRole::withoutTenant()->create([
    'name' => 'widget_specialist',
    'title' => 'Widget Specialist',
    'description' => 'Specialized role for Widget Inc',
    'tenant_id' => $tenant->id,  // Explicitly set tenant
]);

// Find and update a tenant-specific role
$existingRole = KeystoneRole::withoutTenant()
    ->where('tenant_id', $tenant->id)
    ->where('name', 'manager')
    ->first();

$existingRole->update([
    'description' => 'Updated description by super-admin',
]);

// Delete a tenant-specific role
$roleToDelete = KeystoneRole::withoutTenant()
    ->where('tenant_id', $tenant->id)
    ->where('name', 'deprecated_role')
    ->first();

$roleToDelete->delete();
```

### Example 12: Assigning Global Role to User

```php
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;

// Super-admin assigning a global role to a user in any tenant
$user = User::find($userId);
$globalRole = KeystoneRole::withoutTenant()
    ->whereNull('tenant_id')
    ->where('name', 'support_agent')
    ->first();

$user->assignRole($globalRole);

// Verify the role assignment includes tenant_id in pivot
$pivot = DB::table('model_has_roles')
    ->where('model_id', $user->id)
    ->where('role_id', $globalRole->id)
    ->first();

// pivot->tenant_id will be the user's tenant_id (not NULL)
// This tracks which tenant the assignment belongs to, even for global roles
```

## Common Patterns

### Pattern 1: Seed Global Roles in Database Seeder

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create global permissions
        $globalPermissions = [
            ['name' => 'manage-system', 'title' => 'Manage System'],
            ['name' => 'view-all-tenants', 'title' => 'View All Tenants'],
            ['name' => 'manage-billing', 'title' => 'Manage Billing'],
        ];

        foreach ($globalPermissions as $permData) {
            KeystonePermission::withoutTenant()->firstOrCreate(
                ['name' => $permData['name'], 'tenant_id' => null],
                array_merge($permData, ['tenant_id' => null])
            );
        }

        // Create global super admin role
        $superAdmin = KeystoneRole::withoutTenant()->firstOrCreate(
            ['name' => 'super_administrator', 'tenant_id' => null],
            [
                'title' => 'Super Administrator',
                'description' => 'Full system access',
                'tenant_id' => null,
            ]
        );

        // Attach all global permissions
        $superAdmin->syncPermissions(
            KeystonePermission::withoutTenant()->whereNull('tenant_id')->get()
        );
    }
}
```

### Pattern 2: Tenant Onboarding with Default Roles

```php
namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantOnboardingService
{
    public function createTenantWithDefaultRoles(array $tenantData, array $adminUserData): Tenant
    {
        return DB::transaction(function () use ($tenantData, $adminUserData) {
            // Create tenant
            $tenant = Tenant::create($tenantData);

            // Create admin user for the tenant
            $adminUser = User::create(array_merge($adminUserData, [
                'tenant_id' => $tenant->id,
            ]));

            // Authenticate as the new user to set tenant context
            Auth::login($adminUser);

            // Create default tenant-specific permissions
            $permissions = [
                ['name' => 'view-dashboard', 'title' => 'View Dashboard'],
                ['name' => 'manage-users', 'title' => 'Manage Users'],
                ['name' => 'view-reports', 'title' => 'View Reports'],
                ['name' => 'edit-settings', 'title' => 'Edit Settings'],
            ];

            $createdPermissions = collect($permissions)->map(function ($permData) {
                return KeystonePermission::create($permData);
            });

            // Create tenant-specific admin role
            $tenantAdmin = KeystoneRole::create([
                'name' => 'tenant_administrator',
                'title' => 'Administrator',
                'description' => 'Full access within this organization',
            ]);

            $tenantAdmin->syncPermissions($createdPermissions);

            // Create basic user role
            $basicUser = KeystoneRole::create([
                'name' => 'user',
                'title' => 'User',
                'description' => 'Standard user access',
            ]);

            $basicUser->givePermissionTo(['view-dashboard', 'view-reports']);

            // Assign admin role to the admin user
            $adminUser->assignRole($tenantAdmin);

            return $tenant;
        });
    }
}
```

### Pattern 3: Multi-Tenant Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use BSPDX\Keystone\Facades\Keystone;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Ensure user has a tenant
        if (!auth()->user()->tenant_id) {
            // If user doesn't have a tenant and isn't a super-admin, deny access
            if (!Keystone::canBypassPermissions(auth()->user())) {
                abort(403, 'You must be assigned to an organization');
            }
        }

        return $next($request);
    }
}
```

### Pattern 4: Listing Roles with Tenant Information

```php
namespace App\Http\Controllers\Admin;

use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Facades\Keystone;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        // Regular users see only their tenant's roles + global roles
        if (!Keystone::canBypassPermissions($request->user())) {
            $roles = KeystoneRole::with('permissions')->get();

            return view('admin.roles.index', [
                'roles' => $roles,
                'isSuperAdmin' => false,
            ]);
        }

        // Super-admins see all roles across all tenants
        $roles = KeystoneRole::withoutTenant()
            ->with(['permissions', 'users'])
            ->get()
            ->groupBy('tenant_id');

        // Separate global from tenant-specific
        $globalRoles = $roles->get(null, collect());
        $tenantRoles = $roles->except(null);

        return view('admin.roles.index', [
            'globalRoles' => $globalRoles,
            'tenantRoles' => $tenantRoles,
            'isSuperAdmin' => true,
        ]);
    }
}
```

## Best Practices

### 1. Always Use Service Layer for Complex Operations

```php
// Good: Use RoleService
use BSPDX\Keystone\Services\RoleService;

$roleService = app(RoleService::class);
$role = $roleService->create(['name' => 'manager', 'title' => 'Manager']);

// Avoid: Direct model manipulation for complex operations
$role = new KeystoneRole();
$role->name = 'manager';
$role->save();  // May miss validation or tenant context
```

### 2. Explicitly Set tenant_id = null for Global Roles

```php
// Good: Clear intent
$globalRole = KeystoneRole::withoutTenant()->create([
    'name' => 'super_admin',
    'tenant_id' => null,  // Explicitly global
]);

// Avoid: Ambiguous
$role = KeystoneRole::create(['name' => 'super_admin']);
// Is this global or tenant-specific? Depends on auth context.
```

### 3. Use Middleware for Authorization

```php
// Good: Declarative and testable
Route::middleware(['auth', 'permission:manage-users'])->group(function () {
    Route::resource('users', UserController::class);
});

// Avoid: Manual checks in every method
public function index() {
    if (!auth()->user()->hasPermissionTo('manage-users')) {
        abort(403);
    }
    // ...
}
```

### 4. Cache Role and Permission Queries

```php
// Good: Cache tenant-specific roles
use Illuminate\Support\Facades\Cache;

$roles = Cache::remember(
    "tenant.{$tenantId}.roles",
    now()->addHour(),
    fn() => KeystoneRole::with('permissions')->get()
);

// Clear cache when roles change
Cache::forget("tenant.{$tenantId}.roles");
```

### 5. Validate Tenant Context in API Requests

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use BSPDX\Keystone\Facades\Keystone;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        // Ensure user can manage this role
        if (Keystone::canBypassPermissions($this->user())) {
            return true;  // Super-admin can manage any role
        }

        // Regular users can only manage roles in their tenant
        return $role->tenant_id === $this->user()->tenant_id;
    }
}
```

### 6. Test Multi-Tenant Isolation

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use BSPDX\Keystone\Models\KeystoneRole;

class MultiTenantRoleTest extends TestCase
{
    public function test_user_cannot_see_other_tenant_roles()
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        // Create role for Tenant A
        $this->actingAs($userA);
        $roleA = KeystoneRole::create(['name' => 'manager', 'title' => 'Manager']);

        // Switch to Tenant B
        $this->actingAs($userB);
        $visibleRoles = KeystoneRole::all();

        // User B should NOT see Tenant A's manager role
        $this->assertFalse($visibleRoles->contains($roleA));
    }

    public function test_global_roles_accessible_to_all_tenants()
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        // Create global role
        $globalRole = KeystoneRole::withoutTenant()->create([
            'name' => 'support_agent',
            'tenant_id' => null,
        ]);

        // Both users should see the global role
        $this->actingAs($userA);
        $this->assertTrue(KeystoneRole::all()->contains($globalRole));

        $this->actingAs($userB);
        $this->assertTrue(KeystoneRole::all()->contains($globalRole));
    }
}
```

## Next Steps

- [Multi-Tenancy Documentation](../multi-tenancy.md) - Architecture details
- [Role and Permission Management](../roles-and-permissions.md) - Advanced role features
- [Service Layer](../services.md) - Working with Keystone services
- [Testing Guide](../testing.md) - How to test multi-tenant features
