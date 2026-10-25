<?php

namespace Tests\Feature;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionCommandTest extends TestCase
{
    private KeystoneRole $role;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['a', 'b', 'c'] as $name) {
            KeystonePermission::create(['name' => $name]);
        }
        $this->role = KeystoneRole::create(['name' => 'editor'])->givePermissionTo(['a', 'b', 'c']);
    }

    #[Test]
    public function revoke_permission_to_removes_every_given_permission()
    {
        $this->role->revokePermissionTo('a', 'b');

        $this->assertSame(['c'], $this->role->fresh()->getPermissionNames()->all());

        $this->role->revokePermissionTo(['c']);

        $this->assertSame([], $this->role->fresh()->getPermissionNames()->all());
    }

    #[Test]
    public function remove_from_role_removes_every_given_permission()
    {
        app(PermissionServiceInterface::class)->removeFromRole($this->role, ['a', 'b', 'c']);

        $this->assertSame([], $this->role->fresh()->getPermissionNames()->all());
    }

    #[Test]
    public function assign_permission_with_remove_removes_every_given_permission()
    {
        $this->artisan('keystone:assign-permission', ['permission' => ['a', 'b', 'c'], '--to-role' => 'editor', '--remove' => true])
            ->assertSuccessful();

        $this->assertSame([], $this->role->fresh()->getPermissionNames()->all());
    }

    #[Test]
    public function unassign_permission_all_empties_the_role()
    {
        $this->artisan('keystone:unassign-permission', ['--from-role' => 'editor', '--all' => true])
            ->assertSuccessful();

        $this->assertSame([], $this->role->fresh()->getPermissionNames()->all());
    }

    #[Test]
    public function make_role_on_a_non_default_guard_gets_that_guards_permissions()
    {
        $apiPermission = $this->permissionOnBothGuards('publish');

        $this->artisan('keystone:make-role', ['name' => 'publisher', '--guard' => 'api', '-P' => ['publish']])
            ->assertSuccessful();

        $role = KeystoneRole::where('name', 'publisher')->firstOrFail();
        $this->assertSame([$apiPermission->id], $role->permissions->modelKeys());
    }

    #[Test]
    public function assign_permission_to_a_role_on_a_non_default_guard_uses_that_guards_permission()
    {
        $apiPermission = $this->permissionOnBothGuards('publish');
        $role = KeystoneRole::create(['name' => 'publisher', 'guard_name' => 'api']);

        $this->artisan('keystone:assign-permission', ['permission' => ['publish'], '--to-role' => 'publisher', '--guard' => 'api'])
            ->assertSuccessful();

        $this->assertSame([$apiPermission->id], $role->fresh()->permissions->modelKeys());
    }

    #[Test]
    public function unassign_permission_from_a_role_on_a_non_default_guard_removes_that_guards_permission()
    {
        $apiPermission = $this->permissionOnBothGuards('publish');
        $role = KeystoneRole::create(['name' => 'publisher', 'guard_name' => 'api'])->givePermissionTo($apiPermission);

        $this->artisan('keystone:unassign-permission', ['permission' => ['publish'], '--from-role' => 'publisher', '--guard' => 'api'])
            ->assertSuccessful();

        $this->assertSame([], $role->fresh()->permissions->modelKeys());
    }

    #[Test]
    public function a_permission_only_on_the_default_guard_is_not_given_to_a_role_on_another_guard()
    {
        $this->enableApiGuard();
        $role = KeystoneRole::create(['name' => 'publisher', 'guard_name' => 'api']);

        $this->artisan('keystone:assign-permission', ['permission' => ['a'], '--to-role' => 'publisher', '--guard' => 'api'])
            ->expectsOutputToContain("Permission 'a' not found for guard 'api'.")
            ->assertFailed();

        $this->assertSame([], $role->fresh()->permissions->modelKeys());
    }

    /**
     * Create the named permission on the default (web) guard and on api,
     * returning the api one.
     */
    private function permissionOnBothGuards(string $name): KeystonePermission
    {
        $this->enableApiGuard();
        KeystonePermission::create(['name' => $name, 'guard_name' => 'web']);

        return KeystonePermission::create(['name' => $name, 'guard_name' => 'api']);
    }

    private function enableApiGuard(): void
    {
        config(['auth.guards.api' => ['driver' => 'session', 'provider' => 'users']]);
    }
}
