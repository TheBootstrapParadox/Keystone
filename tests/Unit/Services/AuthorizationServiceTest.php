<?php

namespace Tests\Unit\Services;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    private AuthorizationServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AuthorizationServiceInterface::class);

        foreach (['editor', 'admin'] as $name) {
            KeystoneRole::create(['name' => $name]);
        }
        foreach (['edit-posts', 'publish-posts'] as $name) {
            KeystonePermission::create(['name' => $name]);
        }
    }

    #[Test]
    public function assign_roles_to_user_keeps_existing_roles()
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->service->assignRolesToUser($user, ['admin']);

        $this->assertEqualsCanonicalizing(['editor', 'admin'], $user->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function assign_permissions_to_user_keeps_existing_direct_permissions()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('publish-posts');

        $this->service->assignPermissionsToUser($user, ['edit-posts']);

        $this->assertEqualsCanonicalizing(
            ['publish-posts', 'edit-posts'],
            $user->fresh()->permissions->pluck('name')->all()
        );
    }

    #[Test]
    public function sync_roles_for_user_replaces_roles()
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->service->syncRolesForUser($user, ['admin']);

        $this->assertSame(['admin'], $user->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function sync_permissions_for_user_with_empty_set_clears_direct_but_not_role_permissions()
    {
        KeystoneRole::where('name', 'editor')->first()->givePermissionTo('edit-posts');
        $user = User::factory()->create();
        $user->assignRole('editor');
        $user->givePermissionTo('publish-posts');

        $this->service->syncPermissionsForUser($user, []);

        $user = $user->fresh();
        $this->assertCount(0, $user->permissions);
        $this->assertTrue($user->hasPermissionTo('edit-posts'));
    }

    #[Test]
    public function service_role_checks_bypass_for_super_admin()
    {
        KeystoneRole::create(['name' => 'super-admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->assertTrue($this->service->userHasRole($superAdmin, 'editor'));
        $this->assertTrue($this->service->userHasAnyRole($superAdmin, ['editor']));
        $this->assertTrue($this->service->userHasAllRoles($superAdmin, ['editor', 'admin']));
    }

    #[Test]
    public function service_role_check_is_literal_for_other_users()
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->userHasRole($user, 'editor'));
    }
}
