<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\UuidUser;
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

    #[Test]
    public function a_user_can_be_found_by_email()
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->artisan('keystone:assign-role', ['user' => 'jane@example.com', 'role' => ['editor']])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('editor'));
    }

    #[Test]
    #[Group('ddl')]
    public function a_uuid_keyed_user_can_be_found_by_primary_key()
    {
        Schema::create('uuid_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });
        config(['keystone.user.model' => UuidUser::class]);

        // Rebuild Keystone's tables so the pivots get a UUID model_id, as a
        // real install with this user model would
        $migration = require base_path('database/migrations/2024_01_01_00002_create_permission_tables.php');
        $migration->down();
        $migration->up();
        KeystoneRole::create(['name' => 'editor']);
        KeystoneRole::create(['name' => 'admin']);

        $user = UuidUser::create(['email' => 'uuid@example.com']);

        $this->artisan('keystone:assign-role', ['user' => $user->id, 'role' => ['editor']])
            ->assertSuccessful();
        $this->artisan('keystone:assign-role', ['user' => 'uuid@example.com', 'role' => ['admin']])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['editor', 'admin'], $user->fresh()->roles->pluck('name')->all());
    }
}
