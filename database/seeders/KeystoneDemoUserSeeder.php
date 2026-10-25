<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo users with a known password, one per baseline role.
 *
 * For local development and demos only: it refuses to run in production.
 */
class KeystoneDemoUserSeeder extends Seeder
{
    /**
     * Demo accounts: email => [name, role].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected array $users = [
        'superadmin@example.com' => ['Super Admin', 'super-admin'],
        'admin@example.com' => ['Admin User', 'admin'],
        'editor@example.com' => ['Editor User', 'editor'],
        'user@example.com' => ['Regular User', 'user'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('KeystoneDemoUserSeeder creates accounts with a known password and will not run in production.');

            return;
        }

        $this->call(KeystoneSeeder::class);

        $userClass = config('keystone.user.model')
            ?? config('auth.providers.users.model', User::class);

        foreach ($this->users as $email => [$name, $role]) {
            $userClass::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            )->assignRole($role);
        }

        $this->command?->info('Demo users created (password: "password"): '.implode(', ', array_keys($this->users)));
    }
}
