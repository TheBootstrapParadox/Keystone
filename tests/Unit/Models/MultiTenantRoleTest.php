<?php

namespace Tests\Unit\Models;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MultiTenantRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable multi-tenancy for these tests
        config(['keystone.features.multi_tenant' => true]);

        // Ensure Spatie Permission uses array cache in tests
        config(['keystone.permission.cache.store' => 'array']);

        // Clear permission cache
        app(\BSPDX\Keystone\Services\Contracts\CacheServiceInterface::class)->clearPermissionCache();
    }

    #[Test]
    public function global_scope_filters_roles_by_authenticated_user_tenant_id()
    {
        // Create two tenants (using UUIDs as strings)
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        // Create users in different tenants
        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Authenticate as user A and create a role
        Auth::login($userA);
        $roleA = KeystoneRole::create([
            'name' => 'manager',
            'title' => 'Manager',
        ]);

        // Verify the role was created with tenant A's ID
        $this->assertEquals($tenantAId, $roleA->tenant_id);

        // Verify user A can see this role
        $rolesForUserA = KeystoneRole::all();
        $this->assertTrue($rolesForUserA->contains($roleA));

        // Switch to user B
        Auth::login($userB);

        // Verify user B cannot see user A's role
        $rolesForUserB = KeystoneRole::all();
        $this->assertFalse($rolesForUserB->contains($roleA));
    }

    #[Test]
    public function global_scope_includes_roles_with_null_tenant_id()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);

        // Create a global role (tenant_id = NULL)
        $globalRole = KeystoneRole::withoutTenant()->create([
            'name' => 'super_administrator',
            'title' => 'Super Administrator',
            'tenant_id' => null,
        ]);

        // Create a tenant-specific role
        Auth::login($user);
        $tenantRole = KeystoneRole::create([
            'name' => 'manager',
            'title' => 'Manager',
        ]);

        // User should see both global role and their tenant's role
        $roles = KeystoneRole::all();
        $this->assertTrue($roles->contains($globalRole));
        $this->assertTrue($roles->contains($tenantRole));
        $this->assertCount(2, $roles);
    }

    #[Test]
    public function auto_population_of_tenant_id_on_role_creation()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);

        Auth::login($user);

        // Create role without explicitly setting tenant_id
        $role = KeystoneRole::create([
            'name' => 'editor',
            'title' => 'Editor',
        ]);

        // tenant_id should be automatically populated
        $this->assertEquals($tenantId, $role->tenant_id);
    }

    #[Test]
    public function without_tenant_scope_bypasses_tenant_filtering()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create roles for both tenants
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'manager_a', 'title' => 'Manager A']);

        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'manager_b', 'title' => 'Manager B']);

        // Regular query while authenticated as user A
        Auth::login($userA);
        $regularRoles = KeystoneRole::all();
        $this->assertCount(1, $regularRoles);
        $this->assertTrue($regularRoles->contains($roleA));
        $this->assertFalse($regularRoles->contains($roleB));

        // Using withoutTenant() should return ALL roles
        $allRoles = KeystoneRole::withoutTenant()->get();
        $this->assertCount(2, $allRoles);
        $this->assertTrue($allRoles->contains($roleA));
        $this->assertTrue($allRoles->contains($roleB));
    }

    #[Test]
    public function role_uniqueness_enforced_per_tenant()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create 'manager' role in tenant A
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'manager', 'title' => 'Manager']);

        // Create 'manager' role in tenant B (should be allowed - different tenant)
        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'manager', 'title' => 'Manager']);

        // Both roles should exist with different tenant_ids
        $this->assertEquals($tenantAId, $roleA->tenant_id);
        $this->assertEquals($tenantBId, $roleB->tenant_id);
        $this->assertNotEquals($roleA->id, $roleB->id);

        // Verify uniqueness: Try to create duplicate in same tenant (should fail due to unique constraint)
        // Database will throw QueryException due to unique constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);
        KeystoneRole::create(['name' => 'manager', 'title' => 'Manager Duplicate']);
    }

    #[Test]
    public function super_admin_can_access_all_roles_across_tenants()
    {
        config(['keystone.rbac.super_admin_role' => 'super_administrator']);

        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create super admin role (global)
        $superAdminRole = KeystoneRole::withoutTenant()->create([
            'name' => 'super_administrator',
            'title' => 'Super Administrator',
            'tenant_id' => null,
        ]);

        // Create tenant-specific roles
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'manager_a', 'title' => 'Manager A']);

        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'manager_b', 'title' => 'Manager B']);

        // Create super admin user
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole($superAdminRole);

        Auth::login($superAdmin);

        // Super admin should be able to use withoutTenant() to see all roles
        $allRoles = KeystoneRole::withoutTenant()->get();
        $this->assertCount(3, $allRoles); // super_administrator, manager_a, manager_b
        $this->assertTrue($allRoles->contains($superAdminRole));
        $this->assertTrue($allRoles->contains($roleA));
        $this->assertTrue($allRoles->contains($roleB));

        // Verify super admin can identify themselves
        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    #[Test]
    public function roles_without_authenticated_user_return_all_roles()
    {
        // Create global role
        $globalRole = KeystoneRole::withoutTenant()->create([
            'name' => 'global_role',
            'title' => 'Global Role',
            'tenant_id' => null,
        ]);

        // Create tenant-specific role
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        Auth::login($user);
        $tenantRole = KeystoneRole::create(['name' => 'tenant_role', 'title' => 'Tenant Role']);

        // Logout (no authenticated user)
        Auth::logout();

        // When no user is authenticated, global scope doesn't apply, so all roles are visible
        // This is expected behavior - tenant filtering only applies when a user is authenticated
        $roles = KeystoneRole::all();
        $this->assertTrue($roles->contains($globalRole));
        $this->assertTrue($roles->contains($tenantRole));
    }

    #[Test]
    public function creating_role_with_explicit_tenant_id_uses_provided_value()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        Auth::login($user);

        // Create role with explicit tenant_id
        $differentTenantId = '019c17d7-2b98-82cf-b7b5-82abc123def0';
        $role = KeystoneRole::withoutTenant()->create([
            'name' => 'explicit_role',
            'title' => 'Explicit Role',
            'tenant_id' => $differentTenantId,
        ]);

        // Should use the explicitly provided tenant_id, not the authenticated user's
        $this->assertEquals($differentTenantId, $role->tenant_id);
    }
}
