<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\PermissionRegistrar;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Whenever no guard is named, Keystone uses auth.defaults.guard — for
 * creation, checks, lookups, and the super-admin check alike.
 */
class DefaultGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.guards.api' => ['driver' => 'session', 'provider' => 'users'],
            'auth.defaults.guard' => 'api',
        ]);
    }

    #[Test]
    public function permissions_created_without_a_guard_use_the_default_and_match_checks()
    {
        KeystonePermission::create(['name' => 'via-model']);
        app(PermissionServiceInterface::class)->create('via-service');
        $this->artisan('keystone:make-permission', ['name' => 'via-command'])->assertSuccessful();

        $user = User::factory()->create();
        $user->givePermissionTo(['via-model', 'via-service', 'via-command']);

        foreach (['via-model', 'via-service', 'via-command'] as $name) {
            $this->assertSame('api', KeystonePermission::where('name', $name)->value('guard_name'));
            $this->assertTrue($user->hasPermissionTo($name), "{$name} should match on the default guard");
        }
        $this->assertTrue(app(PermissionRegistrar::class)->permissionExists('via-model'));
    }

    #[Test]
    public function roles_created_without_a_guard_use_the_default_and_match_checks()
    {
        $role = app(RoleServiceInterface::class)->create('editor');
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->assertSame('api', $role->guard_name);
        $this->assertTrue($user->hasRole('editor'));
        $this->assertSame('editor', app(RoleServiceInterface::class)->findByName('editor')?->name);
    }

    #[Test]
    public function an_explicit_guard_mismatch_is_still_false()
    {
        $permission = KeystonePermission::create(['name' => 'export', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->assertFalse($user->hasPermissionTo('export'));
        $this->assertTrue($user->hasPermissionTo('export', 'web'));
    }

    #[Test]
    public function the_super_admin_role_must_be_on_the_default_guard()
    {
        $webSuperAdmin = KeystoneRole::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($webSuperAdmin);

        $this->assertFalse($user->isSuperAdmin());

        $user->assignRole(KeystoneRole::create(['name' => 'super-admin']));

        $this->assertTrue($user->fresh()->isSuperAdmin());
    }
}
