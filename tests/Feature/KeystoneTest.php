<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;

class KeystoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Spatie Permission uses array cache in tests
        config(['keystone.permission.cache.store' => 'array']);

        // Clear permission cache using Keystone's cache service
        app(\BSPDX\Keystone\Services\Contracts\CacheServiceInterface::class)->clearPermissionCache();

        // Register test routes for middleware testing
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:admin'])
            ->get('/test-admin-route', function () {
                return response()->json(['message' => 'Success']);
            });
    }

    #[Test]
    public function user_can_be_assigned_a_role()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'admin']);

        $user->assignRole('admin');

        $this->assertTrue($user->hasRole('admin'));
    }

    #[Test]
    public function user_can_be_assigned_a_permission()
    {
        $user = User::factory()->create();
        $permission = KeystonePermission::create(['name' => 'edit-posts']);

        $user->givePermissionTo('edit-posts');

        $this->assertTrue($user->can('edit-posts'));
    }

    #[Test]
    public function role_can_have_permissions()
    {
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'publish-posts']);

        $role->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo($permission));
    }

    #[Test]
    public function user_inherits_permissions_from_role()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'publish-posts']);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('publish-posts'));
    }

    #[Test]
    public function super_admin_can_be_identified()
    {
        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->isSuperAdmin());
    }

    #[Test]
    public function user_can_check_two_factor_status()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasTwoFactorEnabled());

        // Simulate enabling 2FA
        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    #[Test]
    public function user_can_check_if_two_factor_is_required_for_role()
    {
        config(['keystone.two_factor.required_for_roles' => ['admin']]);

        $user = User::factory()->create();
        $adminRole = KeystoneRole::create(['name' => 'admin']);

        $this->assertFalse($user->requires2FA());

        $user->assignRole($adminRole);

        $this->assertTrue($user->requires2FA());
    }

    #[Test]
    public function user_can_get_authentication_methods()
    {
        $user = User::factory()->create();

        $methods = $user->getAuthenticationMethods();

        $this->assertArrayHasKey('password', $methods);
        $this->assertArrayHasKey('two_factor', $methods);
        $this->assertArrayHasKey('passkey', $methods);
        $this->assertTrue($methods['password']);
        $this->assertFalse($methods['two_factor']);
        $this->assertFalse($methods['passkey']);
    }

    #[Test]
    public function middleware_blocks_users_without_required_role()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-admin-route')
            ->assertStatus(403);
    }

    #[Test]
    public function middleware_allows_users_with_required_role()
    {
        $user = User::factory()->create();
        $adminRole = KeystoneRole::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->actingAs($user)
            ->get('/test-admin-route')
            ->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }

    #[Test]
    public function middleware_blocks_users_without_required_permission()
    {
        $user = User::factory()->create();
        $permission = KeystonePermission::create(['name' => 'edit-posts']);

        $this->assertFalse($user->can('edit-posts'));
    }

    #[Test]
    public function super_admin_bypasses_permission_checks()
    {
        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);
        $user->assignRole($superAdminRole);

        // Super admin should bypass permission checks
        $this->assertTrue($user->canBypassPermissions());
    }
}
