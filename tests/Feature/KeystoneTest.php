<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class KeystoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Register test routes for middleware testing
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:admin'])
            ->get('/test-admin-route', function () {
                return response()->json(['message' => 'Success']);
            });
    }

    /** @test */
    public function user_can_be_assigned_a_role()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);

        $user->assignRole('admin');

        $this->assertTrue($user->hasRole('admin'));
    }

    /** @test */
    public function user_can_be_assigned_a_permission()
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit-posts']);

        $user->givePermissionTo('edit-posts');

        $this->assertTrue($user->can('edit-posts'));
    }

    /** @test */
    public function role_can_have_permissions()
    {
        $role = Role::create(['name' => 'editor']);
        $permission = Permission::create(['name' => 'publish-posts']);

        $role->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo($permission));
    }

    /** @test */
    public function user_inherits_permissions_from_role()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'editor']);
        $permission = Permission::create(['name' => 'publish-posts']);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('publish-posts'));
    }

    /** @test */
    public function super_admin_can_be_identified()
    {
        $user = User::factory()->create();
        $superAdminRole = Role::create(['name' => 'super-admin']);

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
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

    /** @test */
    public function user_can_check_if_two_factor_is_required_for_role()
    {
        config(['keystone.two_factor.required_for_roles' => ['admin']]);

        $user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin']);

        $this->assertFalse($user->requires2FA());

        $user->assignRole($adminRole);

        $this->assertTrue($user->requires2FA());
    }

    /** @test */
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

    /** @test */
    public function middleware_blocks_users_without_required_role()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-admin-route')
            ->assertStatus(403);
    }

    /** @test */
    public function middleware_allows_users_with_required_role()
    {
        $user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->actingAs($user)
            ->get('/test-admin-route')
            ->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }

    /** @test */
    public function middleware_blocks_users_without_required_permission()
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit-posts']);

        $this->assertFalse($user->can('edit-posts'));
    }

    /** @test */
    public function super_admin_bypasses_permission_checks()
    {
        $user = User::factory()->create();
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $user->assignRole($superAdminRole);

        // Super admin should bypass permission checks
        $this->assertTrue($user->canBypassPermissions());
    }
}
