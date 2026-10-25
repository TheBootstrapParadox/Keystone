<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\KeystoneServiceProvider;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Database\Seeders\KeystoneDemoUserSeeder;
use Database\Seeders\KeystoneSeeder;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeederTest extends TestCase
{
    #[Test]
    public function the_baseline_seeder_creates_roles_and_permissions_but_no_users()
    {
        $this->seed(KeystoneSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertTrue(KeystoneRole::where('name', 'super-admin')->exists());
        $this->assertTrue(KeystonePermission::where('name', 'assign-roles')->exists());
    }

    #[Test]
    public function the_baseline_seeder_is_idempotent()
    {
        $this->seed(KeystoneSeeder::class);
        $roles = KeystoneRole::count();
        $permissions = KeystonePermission::count();

        $this->seed(KeystoneSeeder::class);

        $this->assertSame($roles, KeystoneRole::count());
        $this->assertSame($permissions, KeystonePermission::count());
    }

    #[Test]
    public function the_demo_user_seeder_creates_one_user_per_role()
    {
        $this->seed(KeystoneDemoUserSeeder::class);

        $this->assertSame(4, User::count());
        $this->assertTrue(User::where('email', 'superadmin@example.com')->first()->isSuperAdmin());
        $this->assertTrue(User::where('email', 'editor@example.com')->first()->hasRole('editor'));
    }

    #[Test]
    public function the_demo_user_seeder_refuses_to_run_in_production()
    {
        $this->app['env'] = 'production';

        // --force is how someone would push past db:seed's production prompt
        $this->artisan('db:seed', ['--class' => KeystoneDemoUserSeeder::class, '--force' => true]);

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function only_keystones_seeders_are_published()
    {
        $published = collect(ServiceProvider::pathsToPublish(KeystoneServiceProvider::class, 'keystone-seeders'))
            ->map(fn ($to, $from) => basename($from))
            ->values()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['KeystoneDemoUserSeeder.php', 'KeystoneSeeder.php'], $published);
    }
}
