<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\RolePermissionController;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePermissionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache using Keystone's cache service
        app(CacheServiceInterface::class)->clearPermissionCache();

        // Register the two safe GET routes from routes/api.php directly, so this
        // test exercises the actual controller actions without depending on any
        // particular auth guard being configured (see routes/api.php's comment
        // about bringing your own guard).
        Route::middleware(['web'])->group(function () {
            Route::get('/roles', [RolePermissionController::class, 'roles'])
                ->name('api.roles.index');

            Route::get('/permissions', [RolePermissionController::class, 'permissions'])
                ->name('api.permissions.index');

            Route::post('/users/{user}/roles', [RolePermissionController::class, 'assignRoles']);
            Route::put('/users/{user}/roles', [RolePermissionController::class, 'syncRoles']);
            Route::post('/users/{user}/permissions', [RolePermissionController::class, 'assignPermissions']);
            Route::put('/users/{user}/permissions', [RolePermissionController::class, 'syncPermissions']);
            Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'assignPermissionsToRole']);
            Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'syncRolePermissions']);
        });
    }

    #[Test]
    public function roles_endpoint_returns_roles_with_permissions_and_user_count()
    {
        $user = User::factory()->create();

        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'publish-posts']);
        $role->givePermissionTo($permission);

        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson('/roles');

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'editor',
            'users_count' => 1,
        ]);

        $roles = $response->json('roles');
        $editor = collect($roles)->firstWhere('name', 'editor');

        $this->assertNotNull($editor);
        $this->assertSame(['publish-posts'], $editor['permissions']);
        $this->assertSame(1, $editor['users_count']);
    }

    #[Test]
    public function permissions_endpoint_returns_created_permissions()
    {
        $user = User::factory()->create();

        KeystonePermission::create(['name' => 'edit-posts']);

        $response = $this->actingAs($user)->getJson('/permissions');

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'edit-posts',
        ]);
    }

    #[Test]
    public function post_user_roles_adds_without_removing_existing_roles()
    {
        [$caller, $target] = $this->usersWithRoles(['editor', 'admin']);
        $target->assignRole('editor');

        $this->actingAs($caller)->postJson("/users/{$target->id}/roles", ['roles' => ['admin']])->assertOk();

        $this->assertEqualsCanonicalizing(['editor', 'admin'], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function post_user_roles_does_not_duplicate_an_already_held_role()
    {
        [$caller, $target] = $this->usersWithRoles(['editor']);
        $target->assignRole('editor');

        $this->actingAs($caller)->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])->assertOk();

        $this->assertSame(['editor'], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function post_user_roles_rejects_an_empty_array()
    {
        [$caller, $target] = $this->usersWithRoles(['editor']);

        $this->actingAs($caller)->postJson("/users/{$target->id}/roles", ['roles' => []])->assertStatus(422);
    }

    #[Test]
    public function put_user_roles_replaces_roles()
    {
        [$caller, $target] = $this->usersWithRoles(['editor', 'admin']);
        $target->assignRole('editor');

        $this->actingAs($caller)->putJson("/users/{$target->id}/roles", ['roles' => ['admin']])->assertOk();

        $this->assertSame(['admin'], $target->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function put_user_roles_with_empty_array_clears_roles()
    {
        [$caller, $target] = $this->usersWithRoles(['editor']);
        $target->assignRole('editor');

        $this->actingAs($caller)->putJson("/users/{$target->id}/roles", ['roles' => []])->assertOk();

        $this->assertCount(0, $target->fresh()->roles);
    }

    #[Test]
    public function post_user_permissions_adds_and_put_replaces()
    {
        [$caller, $target] = $this->usersWithRoles([]);
        KeystonePermission::create(['name' => 'edit-posts']);
        KeystonePermission::create(['name' => 'publish-posts']);
        $target->givePermissionTo('publish-posts');

        $this->actingAs($caller)->postJson("/users/{$target->id}/permissions", ['permissions' => ['edit-posts']])->assertOk();
        $this->assertEqualsCanonicalizing(
            ['publish-posts', 'edit-posts'],
            $target->fresh()->permissions->pluck('name')->all()
        );

        $this->actingAs($caller)->putJson("/users/{$target->id}/permissions", ['permissions' => ['edit-posts']])->assertOk();
        $this->assertSame(['edit-posts'], $target->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function put_user_permissions_without_the_key_is_rejected()
    {
        [$caller, $target] = $this->usersWithRoles([]);

        $this->actingAs($caller)->putJson("/users/{$target->id}/permissions", [])->assertStatus(422);
    }

    #[Test]
    public function post_role_permissions_adds_and_put_replaces()
    {
        $caller = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'editor']);
        KeystonePermission::create(['name' => 'view-posts']);
        KeystonePermission::create(['name' => 'edit-posts']);
        $role->givePermissionTo('view-posts');

        $this->actingAs($caller)->postJson("/roles/{$role->id}/permissions", ['permissions' => ['edit-posts']])
            ->assertOk()
            ->assertJsonCount(2, 'role.permissions');
        $this->assertEqualsCanonicalizing(['view-posts', 'edit-posts'], $role->fresh()->getPermissionNames()->all());

        $this->actingAs($caller)->putJson("/roles/{$role->id}/permissions", ['permissions' => ['edit-posts']])
            ->assertOk()
            ->assertJsonCount(1, 'role.permissions');
        $this->assertSame(['edit-posts'], $role->fresh()->getPermissionNames()->all());
    }

    /**
     * Create the given roles plus a caller and a target user.
     *
     * @return array{0: User, 1: User}
     */
    private function usersWithRoles(array $roles): array
    {
        foreach ($roles as $name) {
            KeystoneRole::create(['name' => $name]);
        }

        return [User::factory()->create(), User::factory()->create()];
    }
}
