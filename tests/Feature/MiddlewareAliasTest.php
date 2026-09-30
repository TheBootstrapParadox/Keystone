<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Middleware\EnsureHasPermission;
use BSPDX\Keystone\Http\Middleware\EnsureHasRole;
use BSPDX\Keystone\KeystoneServiceProvider;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MiddlewareAliasTest extends TestCase
{
    #[Test]
    public function namespaced_aliases_are_registered()
    {
        $aliases = app('router')->getMiddleware();

        $this->assertSame(EnsureHasRole::class, $aliases['keystone.role'] ?? null);
        $this->assertSame(EnsureHasPermission::class, $aliases['keystone.permission'] ?? null);
        $this->assertArrayNotHasKey('keystone.feature', $aliases);
    }

    #[Test]
    public function the_namespaced_role_alias_protects_routes()
    {
        KeystoneRole::create(['name' => 'admin']);
        Route::middleware(['web', 'keystone.role:admin'])->get('/ns-admin', fn () => 'ok');
        $admin = User::factory()->create()->assignRole('admin');
        $other = User::factory()->create();

        $this->actingAs($other)->get('/ns-admin')->assertForbidden();
        $this->actingAs($admin)->get('/ns-admin')->assertOk();
    }

    #[Test]
    public function an_application_role_alias_is_not_overridden()
    {
        app('router')->aliasMiddleware('role', AppRoleMiddleware::class);

        (new KeystoneServiceProvider($this->app))->boot();

        $aliases = app('router')->getMiddleware();
        $this->assertSame(AppRoleMiddleware::class, $aliases['role']);
        $this->assertSame(EnsureHasRole::class, $aliases['keystone.role']);
    }
}

class AppRoleMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}
