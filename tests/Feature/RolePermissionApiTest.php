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
}
