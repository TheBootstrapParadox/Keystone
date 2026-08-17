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
}
