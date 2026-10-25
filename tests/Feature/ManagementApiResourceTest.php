<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\PermissionController;
use BSPDX\Keystone\Http\Controllers\RoleController;
use BSPDX\Keystone\Http\Controllers\RolePermissionController;
use BSPDX\Keystone\Http\Controllers\UserAccessController;
use BSPDX\Keystone\Http\Controllers\UserPermissionController;
use BSPDX\Keystone\Http\Controllers\UserRoleController;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The shipped management API: resource controllers, response shapes,
 * ordering and pagination, request limits, and transactional role creation.
 */
class ManagementApiResourceTest extends TestCase
{
    private const ROLE_KEYS = ['id', 'name', 'guard_name', 'title', 'description', 'permissions', 'created_at'];

    private const PERMISSION_KEYS = ['id', 'name', 'guard_name', 'title', 'description', 'created_at'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web'])->group(base_path('routes/api.php'));
        Route::getRoutes()->refreshNameLookups();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(KeystoneRole::create(['name' => 'super-admin']));
        KeystonePermission::create(['name' => 'edit-posts']);
        KeystonePermission::create(['name' => 'view-posts']);
    }

    // ------------------------------------------------------------------
    // Controllers are organized by resource
    // ------------------------------------------------------------------

    #[Test]
    public function route_names_resolve_to_the_same_uris()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::first();

        $expected = [
            route('api.roles.index', absolute: false) => '/api/roles',
            route('api.permissions.index', absolute: false) => '/api/permissions',
            route('api.roles.store', absolute: false) => '/api/roles',
            route('api.permissions.store', absolute: false) => '/api/permissions',
            route('api.roles.destroy', $role, false) => "/api/roles/{$role->id}",
            route('api.permissions.destroy', $permission, false) => "/api/permissions/{$permission->id}",
            route('api.users.roles.assign', $user, false) => "/api/users/{$user->id}/roles",
            route('api.users.roles.sync', $user, false) => "/api/users/{$user->id}/roles",
            route('api.users.permissions.assign', $user, false) => "/api/users/{$user->id}/permissions",
            route('api.users.permissions.sync', $user, false) => "/api/users/{$user->id}/permissions",
            route('api.users.roles-permissions', $user, false) => "/api/users/{$user->id}/roles-permissions",
            route('api.roles.permissions.assign', $role, false) => "/api/roles/{$role->id}/permissions",
            route('api.roles.permissions.sync', $role, false) => "/api/roles/{$role->id}/permissions",
        ];

        foreach ($expected as $actual => $uri) {
            $this->assertSame($uri, $actual);
        }
    }

    #[Test]
    public function the_old_controller_actions_no_longer_exist()
    {
        $controllers = [RoleController::class, PermissionController::class, UserRoleController::class,
            UserPermissionController::class, RolePermissionController::class, UserAccessController::class];
        $oldActions = ['roles', 'permissions', 'createRole', 'createPermission', 'assignRoles', 'syncRoles',
            'assignPermissions', 'syncPermissions', 'assignPermissionsToRole', 'syncRolePermissions',
            'userRolesPermissions', 'deleteRole', 'deletePermission'];

        foreach ($controllers as $controller) {
            foreach ($oldActions as $action) {
                $this->assertFalse(method_exists($controller, $action), "{$controller}::{$action} should not exist");
            }
        }
    }

    #[Test]
    public function a_stale_route_to_an_old_action_fails()
    {
        Route::middleware(['web'])->post('/stale/roles', [RolePermissionController::class, 'createRole']);

        $this->actingAs($this->admin)->postJson('/stale/roles', ['name' => 'moderator'])->assertServerError();

        $this->assertFalse(KeystoneRole::where('name', 'moderator')->exists());
    }

    // ------------------------------------------------------------------
    // Response shapes
    // ------------------------------------------------------------------

    #[Test]
    public function a_created_role_has_exactly_the_role_fields()
    {
        $role = $this->actingAs($this->admin)
            ->postJson('/api/roles', ['name' => 'moderator', 'permissions' => ['edit-posts']])
            ->assertCreated()
            ->json('role');

        $this->assertSame(self::ROLE_KEYS, array_keys($role));
        $this->assertSame('moderator', $role['name']);
        $this->assertSame('web', $role['guard_name']);
        $this->assertNull($role['title']);
        $this->assertNull($role['description']);
        $this->assertSame(['edit-posts'], $role['permissions']);
        $this->assertIsoDate($role['created_at']);
    }

    #[Test]
    public function a_created_permission_has_exactly_the_permission_fields()
    {
        $permission = $this->actingAs($this->admin)
            ->postJson('/api/permissions', ['name' => 'publish-posts'])
            ->assertCreated()
            ->json('permission');

        $this->assertSame(self::PERMISSION_KEYS, array_keys($permission));
        $this->assertIsoDate($permission['created_at']);
    }

    #[Test]
    public function listed_roles_match_the_created_role_plus_users_count()
    {
        $created = $this->actingAs($this->admin)
            ->postJson('/api/roles', ['name' => 'moderator', 'permissions' => ['edit-posts']])
            ->json('role');

        $listed = collect($this->getJson('/api/roles')->assertOk()->json('roles'))->firstWhere('name', 'moderator');

        $this->assertSame([...self::ROLE_KEYS, 'users_count'], array_keys($listed));
        $this->assertSame([...$created, 'users_count' => 0], $listed);
    }

    #[Test]
    public function listed_permissions_have_the_permission_fields_plus_role_names()
    {
        KeystoneRole::create(['name' => 'editor'])->givePermissionTo('edit-posts');

        $listed = collect($this->actingAs($this->admin)->getJson('/api/permissions')->assertOk()->json('permissions'))
            ->firstWhere('name', 'edit-posts');

        $this->assertSame([...self::PERMISSION_KEYS, 'roles'], array_keys($listed));
        $this->assertSame(['editor'], $listed['roles']);
        $this->assertIsoDate($listed['created_at']);
    }

    #[Test]
    public function role_permission_routes_return_the_role_shape()
    {
        $role = KeystoneRole::create(['name' => 'editor']);

        foreach (['postJson', 'putJson'] as $method) {
            $returned = $this->actingAs($this->admin)
                ->{$method}("/api/roles/{$role->id}/permissions", ['permissions' => ['edit-posts']])
                ->assertOk()
                ->json('role');

            $this->assertSame(self::ROLE_KEYS, array_keys($returned));
            $this->assertSame(['edit-posts'], $returned['permissions']);
            $this->assertIsoDate($returned['created_at']);
        }
    }

    // ------------------------------------------------------------------
    // Listing roles and permissions
    // ------------------------------------------------------------------

    #[Test]
    public function lists_are_ordered_by_name()
    {
        foreach (['viewer', 'admin', 'editor'] as $name) {
            KeystoneRole::create(['name' => $name]);
        }

        $roles = collect($this->actingAs($this->admin)->getJson('/api/roles')->json('roles'))->pluck('name');
        $permissions = collect($this->getJson('/api/permissions')->json('permissions'))->pluck('name');

        $this->assertSame(['admin', 'editor', 'super-admin', 'viewer'], $roles->all());
        $this->assertSame(['edit-posts', 'view-posts'], $permissions->all());
    }

    #[Test]
    public function a_list_can_be_paginated()
    {
        $this->createRoles(24); // plus super-admin: 25

        $response = $this->actingAs($this->admin)->getJson('/api/roles?per_page=10&page=3')->assertOk();

        $this->assertCount(5, $response->json('roles'));
        $this->assertSame(['current_page' => 3, 'per_page' => 10, 'last_page' => 3, 'total' => 25], $response->json('meta'));
    }

    #[Test]
    public function a_list_is_unpaginated_by_default()
    {
        $this->createRoles(24);

        $response = $this->actingAs($this->admin)->getJson('/api/roles')->assertOk();

        $this->assertCount(25, $response->json('roles'));
        $response->assertJsonMissingPath('meta');
    }

    #[Test]
    public function permissions_can_be_paginated()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/permissions?per_page=1&page=2')->assertOk();

        $this->assertSame(['view-posts'], collect($response->json('permissions'))->pluck('name')->all());
        $this->assertSame(['current_page' => 2, 'per_page' => 1, 'last_page' => 2, 'total' => 2], $response->json('meta'));
    }

    #[Test]
    public function a_page_size_out_of_range_is_rejected()
    {
        $this->actingAs($this->admin)->getJson('/api/permissions?per_page=500')->assertUnprocessable();
        $this->actingAs($this->admin)->getJson('/api/roles?per_page=0')->assertUnprocessable();
    }

    #[Test]
    public function a_page_costs_the_same_queries_whatever_its_size()
    {
        $this->createRoles(60);
        $this->actingAs($this->admin);

        $queriesFor = function (int $perPage): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/roles?per_page={$perPage}")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $queriesFor(1); // warm the per-request caches

        $this->assertSame($queriesFor(10), $queriesFor(50));
    }

    // ------------------------------------------------------------------
    // Name arrays are bounded
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function arrayRoutes(): array
    {
        return [
            'add user roles' => ['postJson', '/api/users/{user}/roles', 'roles'],
            'replace user roles' => ['putJson', '/api/users/{user}/roles', 'roles'],
            'add user permissions' => ['postJson', '/api/users/{user}/permissions', 'permissions'],
            'replace user permissions' => ['putJson', '/api/users/{user}/permissions', 'permissions'],
            'add role permissions' => ['postJson', '/api/roles/{role}/permissions', 'permissions'],
            'replace role permissions' => ['putJson', '/api/roles/{role}/permissions', 'permissions'],
            'create role' => ['postJson', '/api/roles', 'permissions'],
        ];
    }

    #[Test]
    #[DataProvider('arrayRoutes')]
    public function more_than_100_names_are_rejected(string $method, string $uri, string $key)
    {
        [$uri, $snapshot] = $this->arrayRouteTarget($uri, $key);
        $names = array_map(fn (int $i) => "{$key}-{$i}", range(1, 101));

        $this->actingAs($this->admin)->{$method}($uri, ['name' => 'new-role', $key => $names])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($key);

        $this->assertSame($snapshot(), $this->arrayRouteTarget($uri, $key)[1]());
    }

    #[Test]
    #[DataProvider('arrayRoutes')]
    public function a_repeated_name_is_rejected(string $method, string $uri, string $key)
    {
        [$uri, $snapshot] = $this->arrayRouteTarget($uri, $key);
        $name = $key === 'roles' ? 'editor' : 'edit-posts';

        $this->actingAs($this->admin)->{$method}($uri, ['name' => 'new-role', $key => [$name, $name]])
            ->assertUnprocessable();

        $this->assertSame($snapshot(), $this->arrayRouteTarget($uri, $key)[1]());
    }

    #[Test]
    public function the_example_routes_are_rate_limited()
    {
        $this->actingAs($this->admin);

        for ($i = 1; $i <= 60; $i++) {
            $this->getJson('/api/roles')->assertOk();
        }

        $this->getJson('/api/roles')->assertTooManyRequests();
    }

    // ------------------------------------------------------------------
    // Role creation is all-or-nothing; one lookup per name
    // ------------------------------------------------------------------

    #[Test]
    public function a_role_is_not_created_when_attaching_its_permissions_fails()
    {
        $this->app->instance(RoleServiceInterface::class, new class(app('keystone.cache')) extends RoleService
        {
            public function syncPermissions(KeystoneRole $role, array $permissions): KeystoneRole
            {
                throw new RuntimeException('attach failed');
            }
        });

        $this->actingAs($this->admin)
            ->postJson('/api/roles', ['name' => 'moderator', 'permissions' => ['edit-posts']])
            ->assertServerError();

        $this->assertFalse(KeystoneRole::where('name', 'moderator')->exists());
    }

    #[Test]
    public function each_role_name_is_looked_up_once_per_request()
    {
        foreach (['editor', 'author', 'viewer'] as $name) {
            KeystoneRole::create(['name' => $name]);
        }
        $target = User::factory()->create();

        $lookups = 0;
        DB::listen(function ($query) use (&$lookups) {
            // Quote-agnostic: SQLite and PostgreSQL use "roles", MySQL `roles`
            if (preg_match('/from [`"]roles[`"] where [`"]name[`"] = \?/', $query->sql)) {
                $lookups++;
            }
        });

        $this->actingAs($this->admin)
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['editor', 'author', 'viewer']])
            ->assertOk();

        $this->assertSame(3, $lookups);
    }

    private function createRoles(int $count): void
    {
        foreach (range(1, $count) as $i) {
            KeystoneRole::create(['name' => sprintf('role-%02d', $i)]);
        }
    }

    /**
     * The concrete URI for an array route, and a snapshot of what it would change.
     *
     * @return array{string, callable(): array<int, mixed>}
     */
    private function arrayRouteTarget(string $uri, string $key): array
    {
        $user = User::firstWhere('email', 'target@example.com') ?? User::factory()->create(['email' => 'target@example.com']);
        $role = KeystoneRole::firstWhere('name', 'editor') ?? KeystoneRole::create(['name' => 'editor']);

        $snapshot = fn () => [
            DB::table('model_has_roles')->where('model_id', $user->id)->pluck('role_id')->all(),
            DB::table('model_has_permissions')->where('model_id', $user->id)->pluck('permission_id')->all(),
            DB::table('role_has_permissions')->pluck('permission_id')->all(),
            KeystoneRole::pluck('name')->all(),
        ];

        return [str_replace(['{user}', '{role}'], [$user->id, $role->id], $uri), $snapshot];
    }

    private function assertIsoDate(?string $value): void
    {
        $this->assertNotNull($value);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value);
    }
}
