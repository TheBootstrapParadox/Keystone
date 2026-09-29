<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\RolePermissionController;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for a single-tenant install (KEYSTONE_MULTI_TENANT=false).
 *
 * Every method here previously threw "SQLSTATE[42S22]: Unknown column 'tenant_id'"
 * because roles()/permissions()/users() called withPivot('tenant_id')
 * unconditionally, even though the pivot migration only adds that column when
 * multi-tenancy is enabled. Run via: composer test-single-tenant
 */
class SingleTenantApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => false]);
    }

    #[Test]
    public function full_role_and_permission_api_works_without_tenant_id_column()
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
        Route::middleware(['web'])->post('/users/{user}/roles', [RolePermissionController::class, 'assignRoles']);
        KeystoneRole::create(['name' => 'editor']);
        [$caller, $target] = User::factory()->count(2)->create();

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('editor'));
        $this->assertFalse($caller->fresh()->hasRole('editor'));
    }

    #[Test]
    public function post_adds_and_put_replaces_user_roles_without_tenant_columns()
    {
        Route::middleware(['web'])->post('/users/{user}/roles', [RolePermissionController::class, 'assignRoles']);
        Route::middleware(['web'])->put('/users/{user}/roles', [RolePermissionController::class, 'syncRoles']);
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
