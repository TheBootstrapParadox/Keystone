<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;

class HasKeystoneTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /** @test */
    public function it_can_check_if_two_factor_is_enabled()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasTwoFactorEnabled());

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    /** @test */
    public function it_returns_false_when_two_factor_secret_is_null()
    {
        $user = User::factory()->create([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    /** @test */
    public function it_can_check_if_passkeys_are_registered()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPasskeysRegistered());
    }

    /** @test */
    public function it_can_check_if_two_factor_is_required()
    {
        config(['keystone.two_factor.required_for_roles' => ['admin']]);

        $user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin']);

        $this->assertFalse($user->requires2FA());

        $user->assignRole($adminRole);

        $this->assertTrue($user->requires2FA());
    }

    /** @test */
    public function it_returns_false_when_no_roles_require_two_factor()
    {
        config(['keystone.two_factor.required_for_roles' => []]);

        $user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->assertFalse($user->requires2FA());
    }

    /** @test */
    public function it_can_check_if_passkey_is_required()
    {
        config(['keystone.passkey.required_for_roles' => ['admin']]);

        $user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin']);

        $this->assertFalse($user->requiresPasskey());

        $user->assignRole($adminRole);

        $this->assertTrue($user->requiresPasskey());
    }

    /** @test */
    public function it_can_get_authentication_methods()
    {
        $user = User::factory()->create();

        $methods = $user->getAuthenticationMethods();

        $this->assertIsArray($methods);
        $this->assertArrayHasKey('password', $methods);
        $this->assertArrayHasKey('two_factor', $methods);
        $this->assertArrayHasKey('passkey', $methods);
    }

    /** @test */
    public function it_can_identify_super_admin()
    {
        config(['keystone.rbac.super_admin_role' => 'super-admin']);

        $user = User::factory()->create();
        $superAdminRole = Role::create(['name' => 'super-admin']);

        $this->assertFalse($user->isSuperAdmin());

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function it_can_check_if_user_can_bypass_permissions()
    {
        $user = User::factory()->create();
        $superAdminRole = Role::create(['name' => 'super-admin']);

        $this->assertFalse($user->canBypassPermissions());

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->canBypassPermissions());
    }
}
