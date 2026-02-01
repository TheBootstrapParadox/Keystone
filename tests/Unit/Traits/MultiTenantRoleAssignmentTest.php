<?php

namespace Tests\Unit\Traits;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MultiTenantRoleAssignmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable multi-tenancy for these tests
        config([
            'keystone.features.multi_tenant' => true,
        ]);
    }

    #[Test]
    public function role_assignments_include_tenant_id_in_pivot_table()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        Auth::login($user);

        $role = KeystoneRole::create(['name' => 'manager', 'title' => 'Manager']);
        $user->assignRole($role);

        // Check that model_has_roles pivot table includes tenant_id
        $pivotRecord = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('role_id', $role->id)
            ->first();

        $this->assertNotNull($pivotRecord);
        $this->assertEquals($tenantId, $pivotRecord->tenant_id);
    }

    #[Test]
    public function users_can_only_see_their_own_tenant_role_assignments()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create tenant-specific roles
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'manager_a', 'title' => 'Manager A']);
        $userA->assignRole($roleA);

        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'manager_b', 'title' => 'Manager B']);
        $userB->assignRole($roleB);

        // User A should only see their own role
        Auth::login($userA);
        $rolesForUserA = $userA->roles;
        $this->assertCount(1, $rolesForUserA);
        $this->assertTrue($rolesForUserA->contains($roleA));
        $this->assertFalse($rolesForUserA->contains($roleB));

        // User B should only see their own role
        Auth::login($userB);
        $rolesForUserB = $userB->roles;
        $this->assertCount(1, $rolesForUserB);
        $this->assertTrue($rolesForUserB->contains($roleB));
        $this->assertFalse($rolesForUserB->contains($roleA));
    }

    #[Test]
    public function global_roles_are_accessible_to_users_in_any_tenant()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create global role (tenant_id = NULL)
        $globalRole = KeystoneRole::withoutTenant()->create([
            'name' => 'super_admin',
            'title' => 'Super Administrator',
            'tenant_id' => null,
        ]);

        // Assign global role to users in different tenants
        Auth::login($userA);
        $userA->assignRole($globalRole);

        Auth::login($userB);
        $userB->assignRole($globalRole);

        // Both users should have the global role
        Auth::login($userA);
        $this->assertTrue($userA->hasRole('super_admin'));

        Auth::login($userB);
        $this->assertTrue($userB->hasRole('super_admin'));

        // Verify both users can see the global role
        Auth::login($userA);
        $this->assertTrue($userA->roles->contains($globalRole));

        Auth::login($userB);
        $this->assertTrue($userB->roles->contains($globalRole));
    }

    #[Test]
    public function tenant_specific_roles_are_isolated_between_tenants()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create tenant-specific role in tenant A
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'editor', 'title' => 'Editor']);
        $userA->assignRole($roleA);

        // User A has the role
        $this->assertTrue($userA->hasRole('editor'));

        // User B in different tenant should NOT have this role
        Auth::login($userB);
        $this->assertFalse($userB->hasRole('editor'));

        // User B cannot see tenant A's role
        $availableRoles = KeystoneRole::all();
        $this->assertFalse($availableRoles->contains($roleA));
    }

    #[Test]
    public function multiple_users_in_different_tenants_can_have_same_role_name_but_different_role_instances()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create roles with same name in different tenants
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'manager', 'title' => 'Manager A']);
        $userA->assignRole($roleA);

        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'manager', 'title' => 'Manager B']);
        $userB->assignRole($roleB);

        // Roles should have different IDs
        $this->assertNotEquals($roleA->id, $roleB->id);

        // Both users should have 'manager' role but different instances
        Auth::login($userA);
        $this->assertTrue($userA->hasRole('manager'));
        $userARoles = $userA->roles;
        $this->assertCount(1, $userARoles);
        $this->assertEquals($roleA->id, $userARoles->first()->id);

        Auth::login($userB);
        $this->assertTrue($userB->hasRole('manager'));
        $userBRoles = $userB->roles;
        $this->assertCount(1, $userBRoles);
        $this->assertEquals($roleB->id, $userBRoles->first()->id);
    }

    #[Test]
    public function removing_role_only_affects_current_tenant()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create same role name in both tenants
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'editor', 'title' => 'Editor A']);
        $userA->assignRole($roleA);

        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'editor', 'title' => 'Editor B']);
        $userB->assignRole($roleB);

        // Remove role from user A
        Auth::login($userA);
        $userA->removeRole('editor');
        $this->assertFalse($userA->hasRole('editor'));

        // User B should still have their role
        Auth::login($userB);
        $this->assertTrue($userB->hasRole('editor'));
    }

    #[Test]
    public function syncing_roles_respects_tenant_boundaries()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create roles in tenant A
        Auth::login($userA);
        $roleA1 = KeystoneRole::create(['name' => 'editor', 'title' => 'Editor']);
        $roleA2 = KeystoneRole::create(['name' => 'viewer', 'title' => 'Viewer']);

        // Create role in tenant B
        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'admin', 'title' => 'Admin']);

        // User A syncs roles - should only work with tenant A roles
        Auth::login($userA);
        $userA->syncRoles([$roleA1, $roleA2]);

        $this->assertTrue($userA->hasRole('editor'));
        $this->assertTrue($userA->hasRole('viewer'));
        $this->assertFalse($userA->hasRole('admin')); // Tenant B role not accessible

        // User B syncs roles
        Auth::login($userB);
        $userB->syncRoles([$roleB]);

        $this->assertTrue($userB->hasRole('admin'));
        $this->assertFalse($userB->hasRole('editor')); // Tenant A role not accessible
    }
}
