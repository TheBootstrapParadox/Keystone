<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use BSPDX\Keystone\Http\Controllers\ProfileController;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Http\Request;

class ShowPermissionsTest extends TestCase
{
    private function profileViewData(User $user): array
    {
        $request = Request::create('/profile', 'GET');
        $request->setUserResolver(fn () => $user);

        return app(ProfileController::class)->show($request)->getData();
    }

    #[Test]
    public function profile_view_includes_roles_and_permissions_when_flag_enabled(): void
    {
        config(['keystone.features.show_permissions' => true]);

        $data = $this->profileViewData(User::factory()->create());

        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('permissions', $data);
    }

    #[Test]
    public function profile_view_excludes_roles_and_permissions_when_flag_disabled(): void
    {
        config(['keystone.features.show_permissions' => false]);

        $data = $this->profileViewData(User::factory()->create());

        $this->assertArrayNotHasKey('roles', $data);
        $this->assertArrayNotHasKey('permissions', $data);
    }

    #[Test]
    public function profile_view_returns_correct_role_names_when_flag_enabled(): void
    {
        config(['keystone.features.show_permissions' => true]);

        $user = User::factory()->create();
        KeystoneRole::create(['name' => 'editor']);
        $user->assignRole('editor');

        $data = $this->profileViewData($user);

        $this->assertTrue($data['roles']->contains('editor'));
    }

    #[Test]
    public function profile_view_returns_correct_permission_names_when_flag_enabled(): void
    {
        config(['keystone.features.show_permissions' => true]);

        $user = User::factory()->create();
        KeystonePermission::create(['name' => 'edit-posts']);
        $user->givePermissionTo('edit-posts');

        $data = $this->profileViewData($user);

        $this->assertTrue($data['permissions']->contains('edit-posts'));
    }
}
