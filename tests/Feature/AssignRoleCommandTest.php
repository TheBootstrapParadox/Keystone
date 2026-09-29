<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignRoleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        KeystoneRole::create(['name' => 'editor']);
        KeystoneRole::create(['name' => 'admin']);
    }

    #[Test]
    public function default_mode_adds_roles()
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->artisan('keystone:assign-role', ['user' => $user->id, 'role' => ['admin']])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['editor', 'admin'], $user->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function sync_option_replaces_roles()
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->artisan('keystone:assign-role', ['user' => $user->id, 'role' => ['admin'], '--sync' => true])
            ->assertSuccessful();

        $this->assertSame(['admin'], $user->fresh()->roles->pluck('name')->all());
    }
}
