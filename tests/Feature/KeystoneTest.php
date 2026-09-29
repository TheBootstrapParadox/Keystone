<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KeystoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache using Keystone's cache service
        app(CacheServiceInterface::class)->clearPermissionCache();

        // Register test routes for middleware testing
        Route::middleware(['web', 'auth', 'role:admin'])
            ->get('/test-admin-route', function () {
                return response()->json(['message' => 'Success']);
            });

        // Routes without `auth` in front, so Keystone's own guest handling is reached
        Route::middleware(['web', 'role:admin'])
            ->get('/test-guest-role-route', fn () => response()->json(['message' => 'Success']));
        Route::middleware(['web', 'permission:edit-posts'])
            ->get('/test-guest-permission-route', fn () => response()->json(['message' => 'Success']));
    }

    protected function tearDown(): void
    {
        // Reset the static guest-redirect callback so it can't leak between tests
        AuthenticationException::redirectUsing(fn () => null);

        parent::tearDown();
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

    #[Test]
    public function json_guest_gets_401_from_role_and_permission_middleware()
    {
        // No route named `login` exists in the test app, so this also proves
        // the middleware no longer depends on one.
        $this->getJson('/test-guest-role-route')->assertStatus(401);
        $this->getJson('/test-guest-permission-route')->assertStatus(401);
    }

    #[Test]
    public function browser_guest_is_redirected_to_the_login_route_when_one_exists()
    {
        Route::get('/login', fn () => 'login')->name('login');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/test-guest-role-route')->assertRedirect('/login');
        $this->get('/test-guest-permission-route')->assertRedirect('/login');
    }

    #[Test]
    public function browser_guest_follows_the_apps_configured_guest_redirect()
    {
        AuthenticationException::redirectUsing(fn () => '/sign-in');

        $this->get('/test-guest-role-route')->assertRedirect('/sign-in');
        $this->get('/test-guest-permission-route')->assertRedirect('/sign-in');
    }

    #[Test]
    public function super_admin_passes_role_middleware_without_holding_the_role()
    {
        KeystoneRole::create(['name' => 'super-admin']);
        KeystoneRole::create(['name' => 'admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)->getJson('/test-admin-route')->assertOk();
    }

    #[Test]
    public function regular_user_without_the_role_is_forbidden_by_role_middleware()
    {
        KeystoneRole::create(['name' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/test-admin-route')->assertForbidden();
    }
}
