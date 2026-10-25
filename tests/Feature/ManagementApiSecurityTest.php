<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Security rules on the shipped management API (routes/api.php): tenant
 * callers cannot modify global rows, nobody but a super-admin can grant access
 * they do not hold, and names and uniqueness are tenant-aware.
 */
class ManagementApiSecurityTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    private const MANAGEMENT_PERMISSIONS = [
        'view-roles', 'view-permissions', 'create-roles', 'create-permissions', 'delete-roles',
        'delete-permissions', 'assign-roles', 'assign-permissions', 'view-users',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web'])->group(base_path('routes/api.php'));

        foreach (self::MANAGEMENT_PERMISSIONS as $name) {
            KeystonePermission::create(['name' => $name]);
        }
        KeystoneRole::create(['name' => 'super-admin']);
    }

    // ------------------------------------------------------------------
    // Tenant callers cannot modify global rows
    // ------------------------------------------------------------------

    #[Test]
    public function a_tenant_caller_cannot_delete_a_global_role()
    {
        $this->enableMultiTenancy();
        $role = KeystoneRole::create(['name' => 'shared']);
        $caller = $this->caller(['delete-roles'], self::TENANT_A);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}")->assertForbidden();

        $this->assertTrue(KeystoneRole::withoutTenant()->whereKey($role->id)->exists());
    }

    #[Test]
    public function a_tenant_caller_cannot_change_a_global_roles_permissions()
    {
        $this->enableMultiTenancy();
        $role = KeystoneRole::create(['name' => 'shared']);
        KeystonePermission::create(['name' => 'x']);
        $caller = $this->caller(['assign-permissions', 'x'], self::TENANT_A);

        $this->actingAs($caller)->postJson("/api/roles/{$role->id}/permissions", ['permissions' => ['x']])->assertForbidden();
        $this->actingAs($caller)->putJson("/api/roles/{$role->id}/permissions", ['permissions' => ['x']])->assertForbidden();

        $this->assertSame([], $role->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function a_tenant_caller_cannot_delete_a_global_permission()
    {
        $this->enableMultiTenancy();
        $permission = KeystonePermission::create(['name' => 'shared-permission']);
        $caller = $this->caller(['delete-permissions'], self::TENANT_A);

        $this->actingAs($caller)->deleteJson("/api/permissions/{$permission->id}")->assertForbidden();

        $this->assertTrue(KeystonePermission::withoutTenant()->whereKey($permission->id)->exists());
    }

    #[Test]
    public function another_tenants_role_is_hidden_from_a_tenant_caller()
    {
        $this->enableMultiTenancy();
        $role = KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_B]);
        $caller = $this->caller(['delete-roles'], self::TENANT_A);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}")->assertNotFound();

        $this->assertTrue(KeystoneRole::withoutTenant()->whereKey($role->id)->exists());
    }

    #[Test]
    public function a_tenant_caller_can_manage_their_own_tenants_role()
    {
        $this->enableMultiTenancy();
        $role = KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_A]);
        $caller = $this->caller(['delete-roles'], self::TENANT_A);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertFalse(KeystoneRole::withoutTenant()->whereKey($role->id)->exists());
    }

    #[Test]
    public function a_tenantless_caller_can_delete_a_global_role()
    {
        $this->enableMultiTenancy();
        $role = KeystoneRole::create(['name' => 'shared']);
        $caller = $this->caller(['delete-roles']);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertFalse(KeystoneRole::withoutTenant()->whereKey($role->id)->exists());
    }

    // ------------------------------------------------------------------
    // No privilege escalation
    // ------------------------------------------------------------------

    #[Test]
    public function a_caller_cannot_grant_themselves_super_admin()
    {
        $caller = $this->caller(['assign-roles']);

        $this->actingAs($caller)
            ->postJson("/api/users/{$caller->id}/roles", ['roles' => ['super-admin']])
            ->assertForbidden();

        $this->assertFalse($caller->fresh()->isSuperAdmin());
    }

    #[Test]
    public function a_caller_can_grant_a_role_whose_permissions_they_hold()
    {
        $this->roleWith('editor', ['edit-posts', 'view-posts']);
        $caller = $this->caller(['assign-roles', 'edit-posts', 'view-posts']);
        $target = User::factory()->create();

        $this->actingAs($caller)
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('editor'));
    }

    #[Test]
    public function a_caller_cannot_grant_a_role_with_a_permission_they_lack()
    {
        $this->roleWith('billing-admin', ['refund-payments']);
        $caller = $this->caller(['assign-roles']);
        $target = User::factory()->create();

        $response = $this->actingAs($caller)->postJson("/api/users/{$target->id}/roles", ['roles' => ['billing-admin']]);

        $response->assertForbidden();
        $this->assertStringContainsString('billing-admin', $response->json('message'));
        $this->assertSame([], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function a_mixed_role_grant_is_atomic()
    {
        $this->roleWith('editor', ['edit-posts']);
        $this->roleWith('billing-admin', ['refund-payments']);
        $caller = $this->caller(['assign-roles', 'edit-posts']);
        $target = User::factory()->create();

        $this->actingAs($caller)
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['editor', 'billing-admin']])
            ->assertForbidden();

        $this->assertSame([], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function a_caller_cannot_grant_a_direct_permission_they_lack()
    {
        KeystonePermission::create(['name' => 'delete-users']);
        $caller = $this->caller(['assign-permissions']);
        $target = User::factory()->create();

        $response = $this->actingAs($caller)
            ->postJson("/api/users/{$target->id}/permissions", ['permissions' => ['delete-users']]);

        $response->assertForbidden();
        $this->assertStringContainsString('delete-users', $response->json('message'));
        $this->assertSame([], $target->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function a_caller_can_grant_a_direct_permission_they_hold()
    {
        KeystonePermission::create(['name' => 'edit-posts']);
        $caller = $this->caller(['assign-permissions', 'edit-posts']);
        $target = User::factory()->create();

        $this->actingAs($caller)
            ->putJson("/api/users/{$target->id}/permissions", ['permissions' => ['edit-posts']])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasDirectPermission('edit-posts'));
    }

    #[Test]
    public function a_caller_cannot_add_a_permission_they_lack_to_a_role()
    {
        KeystonePermission::create(['name' => 'delete-users']);
        $role = KeystoneRole::create(['name' => 'editor']);
        $caller = $this->caller(['assign-permissions']);

        $this->actingAs($caller)
            ->postJson("/api/roles/{$role->id}/permissions", ['permissions' => ['delete-users']])
            ->assertForbidden();

        $this->assertSame([], $role->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function a_caller_cannot_create_a_role_with_a_permission_they_lack()
    {
        KeystonePermission::create(['name' => 'delete-users']);
        $caller = $this->caller(['create-roles']);

        $this->actingAs($caller)
            ->postJson('/api/roles', ['name' => 'purger', 'permissions' => ['delete-users']])
            ->assertForbidden();

        $this->assertFalse(KeystoneRole::withoutTenant()->where('name', 'purger')->exists());
    }

    #[Test]
    public function only_a_super_admin_can_change_the_super_admin_roles_permissions()
    {
        $role = KeystoneRole::withoutTenant()->where('name', 'super-admin')->first();
        $caller = $this->caller(['assign-permissions', 'view-roles']);

        $this->actingAs($caller)
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => ['view-roles']])
            ->assertForbidden();

        $this->assertSame([], $role->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function a_caller_cannot_remove_super_admin_by_sync()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $caller = $this->caller(['assign-roles']);

        $this->actingAs($caller)
            ->putJson("/api/users/{$superAdmin->id}/roles", ['roles' => []])
            ->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
    }

    #[Test]
    public function keeping_an_already_held_role_on_sync_is_allowed()
    {
        $this->roleWith('billing-admin', ['refund-payments']);
        $this->roleWith('viewer', ['view-posts']);
        $target = User::factory()->create();
        $target->assignRole('billing-admin');
        $caller = $this->caller(['assign-roles', 'view-posts']);

        $this->actingAs($caller)
            ->putJson("/api/users/{$target->id}/roles", ['roles' => ['billing-admin', 'viewer']])
            ->assertOk();

        $this->assertEqualsCanonicalizing(['billing-admin', 'viewer'], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function a_super_admin_caller_is_unrestricted()
    {
        $caller = User::factory()->create();
        $caller->assignRole('super-admin');
        $target = User::factory()->create();

        $this->actingAs($caller)
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['super-admin']])
            ->assertOk();

        $this->assertTrue($target->fresh()->isSuperAdmin());
    }

    // ------------------------------------------------------------------
    // Tenant- and guard-aware validation
    // ------------------------------------------------------------------

    #[Test]
    public function another_tenant_can_create_a_role_with_the_same_name()
    {
        $this->enableMultiTenancy();
        $callerA = $this->caller(['create-roles'], self::TENANT_A);
        $callerB = $this->caller(['create-roles'], self::TENANT_B);

        $this->actingAs($callerA)->postJson('/api/roles', ['name' => 'editor'])->assertCreated();
        $this->actingAs($callerB)->postJson('/api/roles', ['name' => 'editor'])->assertCreated();

        $this->assertSame(2, KeystoneRole::withoutTenant()->where('name', 'editor')->count());
    }

    #[Test]
    public function a_tenant_caller_cannot_create_the_super_admin_role()
    {
        $this->enableMultiTenancy();
        $caller = $this->caller(['create-roles'], self::TENANT_A);

        $this->actingAs($caller)->postJson('/api/roles', ['name' => 'super-admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, KeystoneRole::withoutTenant()->where('name', 'super-admin')->count());
    }

    #[Test]
    public function a_duplicate_name_in_the_same_tenant_is_rejected()
    {
        KeystoneRole::create(['name' => 'editor']);
        $caller = $this->caller(['create-roles', 'create-permissions']);

        $this->actingAs($caller)->postJson('/api/roles', ['name' => 'editor'])->assertUnprocessable();
        $this->actingAs($caller)->postJson('/api/permissions', ['name' => 'view-roles'])->assertUnprocessable();
    }

    #[Test]
    public function an_unknown_guard_is_rejected()
    {
        $caller = $this->caller(['create-roles']);

        $this->actingAs($caller)
            ->postJson('/api/roles', ['name' => 'editor', 'guard_name' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guard_name');
    }

    #[Test]
    public function a_role_name_from_another_tenant_is_rejected_for_a_user()
    {
        $this->enableMultiTenancy();
        KeystoneRole::create(['name' => 'billing', 'tenant_id' => self::TENANT_A]);
        $target = User::factory()->create(['tenant_id' => self::TENANT_B]);
        $caller = $this->caller(['assign-roles']);

        $this->actingAs($caller)
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['billing']])
            ->assertUnprocessable();

        $this->assertSame([], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function a_permission_name_from_another_tenant_is_rejected_for_a_role()
    {
        $this->enableMultiTenancy();
        KeystonePermission::create(['name' => 'billing', 'tenant_id' => self::TENANT_A]);
        $role = KeystoneRole::create(['name' => 'clerk', 'tenant_id' => self::TENANT_B]);
        $caller = $this->caller(['assign-permissions']);

        $this->actingAs($caller)
            ->postJson("/api/roles/{$role->id}/permissions", ['permissions' => ['billing']])
            ->assertUnprocessable();

        $this->assertSame([], $role->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function creating_a_role_with_another_tenants_permission_is_rejected()
    {
        $this->enableMultiTenancy();
        KeystonePermission::create(['name' => 'billing', 'tenant_id' => self::TENANT_A]);
        $caller = $this->caller(['create-roles'], self::TENANT_B);

        $this->actingAs($caller)
            ->postJson('/api/roles', ['name' => 'clerk', 'permissions' => ['billing']])
            ->assertUnprocessable();

        $this->assertFalse(KeystoneRole::withoutTenant()->where('name', 'clerk')->exists());
    }

    // ------------------------------------------------------------------
    // Listing
    // ------------------------------------------------------------------

    #[Test]
    public function users_count_only_counts_the_tenant_callers_tenant()
    {
        $this->enableMultiTenancy();
        KeystoneRole::create(['name' => 'viewer']);
        foreach ([self::TENANT_A, self::TENANT_A, self::TENANT_B, self::TENANT_B, self::TENANT_B] as $tenant) {
            User::factory()->create(['tenant_id' => $tenant])->assignRole('viewer');
        }
        $caller = $this->caller(['view-roles'], self::TENANT_A);

        $roles = collect($this->actingAs($caller)->getJson('/api/roles')->assertOk()->json('roles'));

        $this->assertSame(2, $roles->firstWhere('name', 'viewer')['users_count']);
    }

    #[Test]
    public function listing_roles_does_not_query_once_per_role()
    {
        $caller = $this->caller(['view-roles']);
        $this->actingAs($caller)->getJson('/api/roles')->assertOk(); // warm up

        $countFor = function () use ($caller) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($caller)->getJson('/api/roles')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $before = $countFor();
        foreach (range(1, 5) as $i) {
            KeystoneRole::create(['name' => "extra-{$i}"]);
        }
        $after = $countFor();

        $this->assertSame($before, $after);
    }

    #[Test]
    public function a_role_without_timestamps_lists_with_a_null_created_at()
    {
        DB::table('roles')->insert(['name' => 'raw', 'guard_name' => 'web']);
        $caller = $this->caller(['view-roles']);

        $roles = collect($this->actingAs($caller)->getJson('/api/roles')->assertOk()->json('roles'));

        $this->assertNull($roles->firstWhere('name', 'raw')['created_at']);
    }

    // ------------------------------------------------------------------
    // Deleting the super-admin role
    // ------------------------------------------------------------------

    #[Test]
    public function deleting_the_super_admin_role_is_forbidden()
    {
        $role = KeystoneRole::withoutTenant()->where('name', 'super-admin')->first();
        $caller = User::factory()->create();
        $caller->assignRole($role);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Cannot delete the super admin role.');

        $this->assertTrue(KeystoneRole::withoutTenant()->whereKey($role->id)->exists());
    }

    #[Test]
    public function unexpected_errors_are_not_reported_as_forbidden()
    {
        $this->withoutExceptionHandling();
        $role = KeystoneRole::create(['name' => 'editor']);
        $caller = $this->caller(['delete-roles']);

        $this->app->instance(RoleServiceInterface::class, new class extends RoleService
        {
            public function __construct() {}

            public function delete(KeystoneRole $role): void
            {
                throw new RuntimeException('SQLSTATE[HY000]: something broke');
            }
        });

        $this->expectException(RuntimeException::class);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}");
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * A user holding the given global permissions directly.
     *
     * @param  list<string>  $permissions
     */
    private function caller(array $permissions, ?string $tenantId = null): User
    {
        $attributes = $tenantId !== null ? ['tenant_id' => $tenantId] : [];
        $user = User::factory()->create($attributes);

        foreach ($permissions as $name) {
            KeystonePermission::withoutTenant()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);

        return $user->fresh();
    }

    /**
     * A global role granting the given permissions (created as needed).
     *
     * @param  list<string>  $permissions
     */
    private function roleWith(string $name, array $permissions): KeystoneRole
    {
        foreach ($permissions as $permission) {
            KeystonePermission::withoutTenant()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        return KeystoneRole::create(['name' => $name])->givePermissionTo($permissions);
    }

    private function enableMultiTenancy(): void
    {
        if (! Schema::hasColumn('roles', 'tenant_id')) {
            $this->markTestSkipped('Requires the multi-tenant schema.');
        }

        config(['keystone.features.multi_tenant' => true]);
    }
}
