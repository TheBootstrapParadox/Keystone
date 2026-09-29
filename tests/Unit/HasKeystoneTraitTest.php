<?php

namespace Tests\Unit;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasKeystoneTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache using Keystone's cache service
        app(CacheServiceInterface::class)->clearPermissionCache();
    }

    #[Test]
    public function it_can_identify_super_admin()
    {
        config(['keystone.rbac.super_admin_role' => 'super-admin']);

        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);

        $this->assertFalse($user->isSuperAdmin());

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->isSuperAdmin());
    }

    #[Test]
    public function it_can_check_if_user_can_bypass_permissions()
    {
        $user = User::factory()->create();
        $superAdminRole = KeystoneRole::create(['name' => 'super-admin']);

        $this->assertFalse($user->canBypassPermissions());

        $user->assignRole($superAdminRole);

        $this->assertTrue($user->canBypassPermissions());
    }

    #[Test]
    public function super_admin_role_checks_are_literal()
    {
        config(['keystone.rbac.super_admin_role' => 'super-admin']);
        KeystoneRole::create(['name' => 'super-admin']);
        KeystoneRole::create(['name' => 'editor']);
        KeystoneRole::create(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->hasRole('nonexistent-role'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertFalse($user->hasAnyRole('editor', 'admin'));
        $this->assertFalse($user->hasAllRoles('super-admin', 'editor'));

        $user->assignRole('editor');

        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasAllRoles('super-admin', 'editor'));
    }

    #[Test]
    public function super_admin_still_passes_every_permission_check()
    {
        KeystoneRole::create(['name' => 'super-admin']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->assertTrue($user->hasPermissionTo('anything'));
        $this->assertTrue($user->hasAnyPermission('anything', 'else'));
        $this->assertTrue($user->hasAllPermissions('anything', 'else'));
        $this->assertTrue($user->hasDirectPermission('anything'));
    }
}
