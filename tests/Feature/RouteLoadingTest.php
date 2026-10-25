<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\KeystoneServiceProvider;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shipped routes/api.php works as-is, whether Keystone loads it
 * (keystone.load_routes) or the app copies it into its own api group.
 */
class RouteLoadingTest extends TestCase
{
    #[Test]
    public function package_loaded_routes_bind_roles()
    {
        $this->loadPackageRoutes();
        $role = KeystoneRole::create(['name' => 'victim']);

        $this->actingAs($this->deleter())->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertFalse(KeystoneRole::whereKey($role->id)->exists());
    }

    #[Test]
    public function package_loaded_routes_return_404_for_an_unknown_role()
    {
        $this->loadPackageRoutes();

        $this->actingAs($this->deleter())->deleteJson('/api/roles/999999')->assertNotFound();
    }

    #[Test]
    public function the_demo_web_routes_are_never_loaded()
    {
        // This repo's own dev app registers routes/web.php; start from an empty
        // collection so only what Keystone loads is present.
        Route::setRoutes(new RouteCollection);

        $this->loadPackageRoutes();

        $uris = collect(Route::getRoutes()->getRoutes())->map->uri();
        $this->assertContains('api/roles', $uris);
        $this->assertNotContains('admin', $uris);
        $this->assertNotContains('posts/edit', $uris);
    }

    #[Test]
    public function the_route_file_still_binds_inside_the_api_group()
    {
        Route::middleware('api')->group(base_path('routes/api.php'));
        $role = KeystoneRole::create(['name' => 'victim']);

        $this->actingAs($this->deleter())->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertFalse(KeystoneRole::whereKey($role->id)->exists());
    }

    private function loadPackageRoutes(): void
    {
        config(['keystone.load_routes' => true]);
        (new KeystoneServiceProvider($this->app))->boot();
        Route::getRoutes()->refreshNameLookups();
    }

    private function deleter(): User
    {
        KeystonePermission::create(['name' => 'delete-roles']);

        return User::factory()->create()->givePermissionTo('delete-roles');
    }
}
