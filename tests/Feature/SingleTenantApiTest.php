<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Exceptions\RoleAlreadyExists;
use BSPDX\Keystone\Http\Controllers\UserRoleController;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for a single-tenant install (KEYSTONE_MULTI_TENANT=false).
 *
 * The tenant columns exist in both modes; in single-tenant mode Keystone
 * neither writes nor filters by them.
 */
class SingleTenantApiTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => false]);
    }

    #[Test]
    public function the_tenant_columns_exist_and_stay_null()
    {
        foreach (['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "{$table}.tenant_id should exist");
        }

        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['tenant_id' => self::TENANT_A]));
        KeystonePermission::create(['name' => 'edit-posts']);
        KeystoneRole::create(['name' => 'editor'])->givePermissionTo('edit-posts');
        $user->assignRole('editor')->givePermissionTo('edit-posts');

        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNotNull('tenant_id')->count(), "{$table} rows should have no tenant");
        }
    }

    #[Test]
    public function a_duplicate_role_name_is_rejected()
    {
        KeystoneRole::create(['name' => 'editor']);

        try {
            KeystoneRole::create(['name' => 'editor']);
            $this->fail('Expected RoleAlreadyExists.');
        } catch (RoleAlreadyExists) {
        }

        $this->assertSame(1, KeystoneRole::where('name', 'editor')->count());
    }

    #[Test]
    public function stored_tenant_values_are_ignored()
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'auditor', 'guard_name' => 'web', 'tenant_id' => self::TENANT_A]);
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['tenant_id' => self::TENANT_B]));

        $this->assertTrue(KeystoneRole::whereKey($roleId)->exists());

        $user->assignRole('auditor');

        $this->assertTrue($user->fresh()->hasRole('auditor'));
    }

    #[Test]
    public function turning_multi_tenancy_on_needs_no_migration()
    {
        config(['keystone.features.multi_tenant' => true]);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);

        $role = KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_A]);
        $user->assignRole('manager');

        $this->assertTrue($user->fresh()->hasRole('manager'));
        $this->assertSame(self::TENANT_A, DB::table('model_has_roles')->where('role_id', $role->id)->value('tenant_id'));
    }

    #[Test]
    public function full_role_and_permission_api_works_in_single_tenant_mode()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'edit-posts']);

        $role->givePermissionTo($permission);
        $this->assertTrue($role->permissions()->get()->contains('name', 'edit-posts'));

        $user->assignRole($role);
        $this->assertTrue($user->roles()->get()->contains('name', 'editor'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($role->users()->get()->contains('id', $user->id));

        $user->givePermissionTo('edit-posts');
        $this->assertTrue($user->permissions()->get()->contains('name', 'edit-posts'));
        $this->assertTrue($user->hasDirectPermission('edit-posts'));
        $this->assertTrue($user->getAllPermissions()->contains('name', 'edit-posts'));

        $user->revokePermissionTo('edit-posts');
        $this->assertFalse($user->fresh()->hasDirectPermission('edit-posts'));

        $user->syncPermissions('edit-posts');
        $this->assertTrue($user->hasDirectPermission('edit-posts'));

        $user->removeRole('editor');
        $this->assertFalse($user->fresh()->hasRole('editor'));

        $user->syncRoles('editor');
        $this->assertTrue($user->hasRole('editor'));
    }

    #[Test]
    public function user_route_assigns_to_the_user_in_the_url()
    {
        Route::middleware(['web'])->post('/users/{user}/roles', [UserRoleController::class, 'store']);
        KeystoneRole::create(['name' => 'editor']);
        [$caller, $target] = User::factory()->count(2)->create();

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('editor'));
        $this->assertFalse($caller->fresh()->hasRole('editor'));
    }

    #[Test]
    public function post_adds_and_put_replaces_user_roles()
    {
        Route::middleware(['web'])->post('/users/{user}/roles', [UserRoleController::class, 'store']);
        Route::middleware(['web'])->put('/users/{user}/roles', [UserRoleController::class, 'update']);
        KeystoneRole::create(['name' => 'editor']);
        KeystoneRole::create(['name' => 'admin']);
        [$caller, $target] = User::factory()->count(2)->create();
        $target->assignRole('editor');

        $this->actingAs($caller)->postJson("/users/{$target->id}/roles", ['roles' => ['admin']])->assertOk();
        $this->assertEqualsCanonicalizing(['editor', 'admin'], $target->fresh()->roles->pluck('name')->all());

        $this->actingAs($caller)->putJson("/users/{$target->id}/roles", ['roles' => ['admin']])->assertOk();
        $this->assertSame(['admin'], $target->fresh()->roles->pluck('name')->all());
    }
}
