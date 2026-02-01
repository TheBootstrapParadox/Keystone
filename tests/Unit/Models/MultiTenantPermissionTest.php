<?php

namespace Tests\Unit\Models;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MultiTenantPermissionTest extends TestCase
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
    public function global_scope_filters_permissions_by_tenant_id()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create permission as user A
        Auth::login($userA);
        $permissionA = KeystonePermission::create([
            'name' => 'edit-users',
            'title' => 'Edit Users',
        ]);

        $this->assertEquals($tenantAId, $permissionA->tenant_id);

        // User A can see their permission
        $permissionsForUserA = KeystonePermission::all();
        $this->assertTrue($permissionsForUserA->contains($permissionA));

        // User B cannot see user A's permission
        Auth::login($userB);
        $permissionsForUserB = KeystonePermission::all();
        $this->assertFalse($permissionsForUserB->contains($permissionA));
    }

    #[Test]
    public function global_scope_includes_global_permissions()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);

        // Create global permission
        $globalPermission = KeystonePermission::withoutTenant()->create([
            'name' => 'manage-system',
            'title' => 'Manage System',
            'tenant_id' => null,
        ]);

        // Create tenant-specific permission
        Auth::login($user);
        $tenantPermission = KeystonePermission::create([
            'name' => 'edit-users',
            'title' => 'Edit Users',
        ]);

        // User should see both global and tenant permissions
        $permissions = KeystonePermission::all();
        $this->assertTrue($permissions->contains($globalPermission));
        $this->assertTrue($permissions->contains($tenantPermission));
        $this->assertCount(2, $permissions);
    }

    #[Test]
    public function auto_population_of_tenant_id_on_permission_creation()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);

        Auth::login($user);

        $permission = KeystonePermission::create([
            'name' => 'delete-posts',
            'title' => 'Delete Posts',
        ]);

        $this->assertEquals($tenantId, $permission->tenant_id);
    }

    #[Test]
    public function without_tenant_scope_works_correctly()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create permissions for both tenants
        Auth::login($userA);
        $permA = KeystonePermission::create(['name' => 'perm_a', 'title' => 'Permission A']);

        Auth::login($userB);
        $permB = KeystonePermission::create(['name' => 'perm_b', 'title' => 'Permission B']);

        // Regular query
        $regularPerms = KeystonePermission::all();
        $this->assertCount(1, $regularPerms);
        $this->assertTrue($regularPerms->contains($permB));

        // Using withoutTenant()
        $allPerms = KeystonePermission::withoutTenant()->get();
        $this->assertCount(2, $allPerms);
        $this->assertTrue($allPerms->contains($permA));
        $this->assertTrue($allPerms->contains($permB));
    }

    #[Test]
    public function permission_uniqueness_per_tenant()
    {
        $tenantAId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $tenantBId = '019c17d7-2b98-82cf-b7b5-82abc123def0';

        $userA = User::factory()->create(['tenant_id' => $tenantAId]);
        $userB = User::factory()->create(['tenant_id' => $tenantBId]);

        // Create 'edit-users' permission in tenant A
        Auth::login($userA);
        $permA = KeystonePermission::create(['name' => 'edit-users', 'title' => 'Edit Users']);

        // Create same permission name in tenant B (should be allowed)
        Auth::login($userB);
        $permB = KeystonePermission::create(['name' => 'edit-users', 'title' => 'Edit Users']);

        $this->assertEquals($tenantAId, $permA->tenant_id);
        $this->assertEquals($tenantBId, $permB->tenant_id);
        $this->assertNotEquals($permA->id, $permB->id);

        // Try to create duplicate in same tenant (should fail due to unique constraint)
        // Database will throw QueryException due to unique constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);
        KeystonePermission::create(['name' => 'edit-users', 'title' => 'Edit Users Duplicate']);
    }

    #[Test]
    public function permissions_without_authenticated_user_return_all_permissions()
    {
        // Create global permission
        $globalPerm = KeystonePermission::withoutTenant()->create([
            'name' => 'global_perm',
            'title' => 'Global Permission',
            'tenant_id' => null,
        ]);

        // Create tenant permission
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        Auth::login($user);
        $tenantPerm = KeystonePermission::create(['name' => 'tenant_perm', 'title' => 'Tenant Permission']);

        Auth::logout();

        // When no user is authenticated, global scope doesn't apply, so all permissions are visible
        // This is expected behavior - tenant filtering only applies when a user is authenticated
        $permissions = KeystonePermission::all();
        $this->assertTrue($permissions->contains($globalPerm));
        $this->assertTrue($permissions->contains($tenantPerm));
    }

    #[Test]
    public function explicit_tenant_id_overrides_auto_population()
    {
        $tenantId = '019c17d6-1a87-71be-a6a4-718da52579e9';
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        Auth::login($user);

        $differentTenantId = '019c17d7-2b98-82cf-b7b5-82abc123def0';
        $permission = KeystonePermission::withoutTenant()->create([
            'name' => 'explicit_perm',
            'title' => 'Explicit Permission',
            'tenant_id' => $differentTenantId,
        ]);

        $this->assertEquals($differentTenantId, $permission->tenant_id);
    }
}
