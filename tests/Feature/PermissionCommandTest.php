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
}
