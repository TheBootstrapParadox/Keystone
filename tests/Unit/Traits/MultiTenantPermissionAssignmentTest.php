<?php

namespace Tests\Unit\Traits;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MultiTenantPermissionAssignmentTest extends TestCase
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
    public function permission_assignments_include_tenant_id_in_pivot_table()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        Auth::login($user);

        $permission = KeystonePermission::create(['name' => 'edit-posts', 'title' => 'Edit Posts']);
        $user->givePermissionTo($permission);

        // Check that model_has_permissions pivot table includes tenant_id
        $pivotRecord = DB::table('model_has_permissions')
            ->where('model_id', $user->id)
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertNotNull($pivotRecord);
        $this->assertEquals($tenantId, $pivotRecord->tenant_id);
    }

    #[Test]
    public function direct_permission_grants_are_tenant_isolated()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create tenant-specific permissions
        Auth::login($userA);
        $permA = KeystonePermission::create(['name' => 'delete-posts', 'title' => 'Delete Posts']);
        $userA->givePermissionTo($permA);

        Auth::login($userB);
        $permB = KeystonePermission::create(['name' => 'publish-posts', 'title' => 'Publish Posts']);
        $userB->givePermissionTo($permB);

        // User A should only have their permission
        Auth::login($userA);
        $this->assertTrue($userA->hasPermissionTo('delete-posts'));
        $this->assertFalse($userA->hasPermissionTo('publish-posts'));

        // User B should only have their permission
        Auth::login($userB);
        $this->assertTrue($userB->hasPermissionTo('publish-posts'));
        $this->assertFalse($userB->hasPermissionTo('delete-posts'));
    }

    #[Test]
    public function global_permissions_accessible_across_tenants()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create global permission (tenant_id = NULL)
        $globalPerm = KeystonePermission::withoutTenant()->create([
            'name' => 'view-system-logs',
            'title' => 'View System Logs',
            'tenant_id' => null,
        ]);

        // Assign global permission to users in different tenants
        Auth::login($userA);
        $userA->givePermissionTo($globalPerm);

        Auth::login($userB);
        $userB->givePermissionTo($globalPerm);

        // Both users should have the global permission
        Auth::login($userA);
        $this->assertTrue($userA->hasPermissionTo('view-system-logs'));

        Auth::login($userB);
        $this->assertTrue($userB->hasPermissionTo('view-system-logs'));
    }

    #[Test]
    public function permission_checks_respect_tenant_boundaries()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create same permission name in both tenants
        Auth::login($userA);
        $permA = KeystonePermission::create(['name' => 'edit-content', 'title' => 'Edit Content A']);
        $userA->givePermissionTo($permA);

        Auth::login($userB);
        $permB = KeystonePermission::create(['name' => 'edit-content', 'title' => 'Edit Content B']);
        $userB->givePermissionTo($permB);

        // Permissions should have different IDs
        $this->assertNotEquals($permA->id, $permB->id);

        // Both users have permission by name, but different instances
        Auth::login($userA);
        $this->assertTrue($userA->hasPermissionTo('edit-content'));
        $userAPerms = $userA->permissions;
        $this->assertCount(1, $userAPerms);
        $this->assertEquals($permA->id, $userAPerms->first()->id);

        Auth::login($userB);
        $this->assertTrue($userB->hasPermissionTo('edit-content'));
        $userBPerms = $userB->permissions;
        $this->assertCount(1, $userBPerms);
        $this->assertEquals($permB->id, $userBPerms->first()->id);
    }

    #[Test]
    public function role_permissions_respect_tenant_isolation()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create role and permission in tenant A
        Auth::login($userA);
        $roleA = KeystoneRole::create(['name' => 'editor', 'title' => 'Editor']);
        $permA = KeystonePermission::create(['name' => 'edit-articles', 'title' => 'Edit Articles']);
        $roleA->givePermissionTo($permA);
        $userA->assignRole($roleA);

        // Create role and permission in tenant B
        Auth::login($userB);
        $roleB = KeystoneRole::create(['name' => 'editor', 'title' => 'Editor']);
        $permB = KeystonePermission::create(['name' => 'edit-pages', 'title' => 'Edit Pages']);
        $roleB->givePermissionTo($permB);
        $userB->assignRole($roleB);

        // User A should have tenant A permissions via role
        Auth::login($userA);
        $this->assertTrue($userA->hasPermissionTo('edit-articles'));
        $this->assertFalse($userA->hasPermissionTo('edit-pages'));

        // User B should have tenant B permissions via role
        Auth::login($userB);
        $this->assertTrue($userB->hasPermissionTo('edit-pages'));
        $this->assertFalse($userB->hasPermissionTo('edit-articles'));
    }

    #[Test]
    public function syncing_permissions_respects_tenant_boundaries()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create permissions in tenant A
        Auth::login($userA);
        $permA1 = KeystonePermission::create(['name' => 'view-reports', 'title' => 'View Reports']);
        $permA2 = KeystonePermission::create(['name' => 'export-data', 'title' => 'Export Data']);

        // Create permission in tenant B
        Auth::login($userB);
        $permB = KeystonePermission::create(['name' => 'manage-users', 'title' => 'Manage Users']);

        // User A syncs permissions - should only work with tenant A permissions
        Auth::login($userA);
        $userA->syncPermissions([$permA1, $permA2]);

        $this->assertTrue($userA->hasPermissionTo('view-reports'));
        $this->assertTrue($userA->hasPermissionTo('export-data'));
        $this->assertFalse($userA->hasPermissionTo('manage-users')); // Tenant B permission not accessible

        // User B syncs permissions
        Auth::login($userB);
        $userB->syncPermissions([$permB]);

        $this->assertTrue($userB->hasPermissionTo('manage-users'));
        $this->assertFalse($userB->hasPermissionTo('view-reports')); // Tenant A permission not accessible
    }

    #[Test]
    public function revoking_permission_only_affects_current_tenant()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create same permission name in both tenants
        Auth::login($userA);
        $permA = KeystonePermission::create(['name' => 'delete-content', 'title' => 'Delete Content A']);
        $userA->givePermissionTo($permA);

        Auth::login($userB);
        $permB = KeystonePermission::create(['name' => 'delete-content', 'title' => 'Delete Content B']);
        $userB->givePermissionTo($permB);

        // Revoke permission from user A
        Auth::login($userA);
        $userA->revokePermissionTo('delete-content');
        $this->assertFalse($userA->hasPermissionTo('delete-content'));

        // User B should still have their permission
        Auth::login($userB);
        $this->assertTrue($userB->hasPermissionTo('delete-content'));
    }
}
