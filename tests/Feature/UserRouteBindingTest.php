<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\UserAccessController;
use BSPDX\Keystone\Http\Controllers\UserPermissionController;
use BSPDX\Keystone\Http\Controllers\UserRoleController;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The {user} segment on the management API must target the user in the URL,
 * never the authenticated caller.
 */
class UserRouteBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web'])->group(function () {
            Route::post('/users/{user}/roles', [UserRoleController::class, 'store']);
            Route::post('/users/{user}/permissions', [UserPermissionController::class, 'store']);
            Route::get('/users/{user}/roles-permissions', [UserAccessController::class, 'show']);
        });

        KeystoneRole::withoutTenant()->create(['name' => 'editor']);
        KeystonePermission::withoutTenant()->create(['name' => 'edit-posts']);
    }

    #[Test]
    public function roles_are_assigned_to_the_user_in_the_url_not_the_caller()
    {
        [$caller, $target] = User::factory()->count(2)->create();

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertOk()
            ->assertJsonPath('user.id', $target->id);

        $this->assertTrue($target->fresh()->hasRole('editor'));
        $this->assertFalse($caller->fresh()->hasRole('editor'));
    }

    #[Test]
    public function permissions_are_assigned_to_the_user_in_the_url_not_the_caller()
    {
        [$caller, $target] = User::factory()->count(2)->create();
        // Callers may only grant what they hold; hold it via a role so the
        // direct-permission assertion below still tells caller from target.
        $caller->assignRole(KeystoneRole::create(['name' => 'writer'])->givePermissionTo('edit-posts'));

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/permissions", ['permissions' => ['edit-posts']])
            ->assertOk()
            ->assertJsonPath('user.id', $target->id);

        $this->assertTrue($target->fresh()->hasDirectPermission('edit-posts'));
        $this->assertFalse($caller->fresh()->hasDirectPermission('edit-posts'));
    }

    #[Test]
    public function roles_permissions_shows_the_user_in_the_url()
    {
        [$caller, $target] = User::factory()->count(2)->create();
        $target->assignRole('editor');

        $this->actingAs($caller)
            ->getJson("/users/{$target->id}/roles-permissions")
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.roles.0.name', 'editor');
    }

    #[Test]
    public function unknown_user_returns_404()
    {
        $caller = User::factory()->create();

        $this->actingAs($caller)
            ->postJson('/users/999999/roles', ['roles' => ['editor']])
            ->assertNotFound();

        $this->assertFalse($caller->fresh()->hasRole('editor'));
    }

    #[Test]
    public function an_app_level_user_route_binding_is_honoured()
    {
        Route::model('user', User::class);
        [$caller, $target] = User::factory()->count(2)->create();

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertOk()
            ->assertJsonPath('user.id', $target->id);

        $this->actingAs($caller)
            ->getJson("/users/{$target->id}/roles-permissions")
            ->assertOk()
            ->assertJsonPath('user.id', $target->id);

        $this->assertTrue($target->fresh()->hasRole('editor'));
    }

    #[Test]
    public function an_app_level_user_route_binding_cannot_bypass_tenant_isolation()
    {
        $this->requireTenantSchema();
        config(['keystone.features.multi_tenant' => true]);
        Route::model('user', User::class);
        $caller = User::factory()->create(['tenant_id' => (string) Str::uuid()]);
        $target = User::factory()->create(['tenant_id' => (string) Str::uuid()]);

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertNotFound();

        $this->assertFalse($target->fresh()->hasRole('editor'));
    }

    #[Test]
    public function tenant_caller_cannot_reach_a_user_in_another_tenant()
    {
        $this->requireTenantSchema();
        config(['keystone.features.multi_tenant' => true]);
        $caller = User::factory()->create(['tenant_id' => (string) Str::uuid()]);
        $target = User::factory()->create(['tenant_id' => (string) Str::uuid()]);

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertNotFound();

        $this->actingAs($caller)
            ->getJson("/users/{$target->id}/roles-permissions")
            ->assertNotFound();

        $this->assertFalse($target->fresh()->hasRole('editor'));
    }

    #[Test]
    public function tenantless_caller_can_reach_a_user_in_any_tenant()
    {
        $this->requireTenantSchema();
        config(['keystone.features.multi_tenant' => true]);
        $caller = User::factory()->create(['tenant_id' => null]);
        $target = User::factory()->create(['tenant_id' => (string) Str::uuid()]);

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['editor']])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('editor'));
    }

    #[Test]
    public function tenantless_caller_assigns_the_target_tenants_role_by_name()
    {
        $this->requireTenantSchema();
        config(['keystone.features.multi_tenant' => true]);
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();
        KeystoneRole::withoutTenant()->create(['name' => 'manager', 'tenant_id' => $tenantA]);
        $roleB = KeystoneRole::withoutTenant()->create(['name' => 'manager', 'tenant_id' => $tenantB]);
        $caller = User::factory()->create(['tenant_id' => null]);
        $target = User::factory()->create(['tenant_id' => $tenantB]);

        $this->actingAs($caller)
            ->postJson("/users/{$target->id}/roles", ['roles' => ['manager']])
            ->assertOk();

        $this->assertSame(
            [$roleB->id],
            DB::table('model_has_roles')->where('model_id', $target->id)->pluck('role_id')->all()
        );
    }

    /**
     * The single-tenant suite migrates without tenant_id columns.
     */
    private function requireTenantSchema(): void
    {
        if (! Schema::hasColumn('model_has_roles', 'tenant_id')) {
            $this->markTestSkipped('Requires the multi-tenant schema.');
        }
    }
}
