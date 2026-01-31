<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChangePasswordCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:change-password
        {user? : The user\'s ID or email address}
        {--password= : The new password (will prompt if not provided)}';

    protected $description = 'Change a user\'s password';

    public function handle(): int
    {
        // Production warning
        if (app()->environment('production')) {
            if (!$this->confirm('You are in production. Are you sure you want to change a password via CLI?')) {
                return self::SUCCESS;
            }
        }

        // Find the user
        $identifier = $this->argument('user') ?? $this->ask('Enter the user\'s ID or email address');

        $user = $this->findUser($identifier);

        if (!$user) {
            $this->error("User '{$identifier}' not found.");
            return self::FAILURE;
        }

        $this->info("Changing password for: {$user->name} ({$user->email})");

        // Get new password
        $password = $this->option('password');
        $generatedPassword = false;

        if (!$password) {
            if ($this->confirm('Generate a random password?', true)) {
                $password = Str::random(16);
                $generatedPassword = true;
            } else {
                $password = $this->secret('Enter the new password');

                // Confirm password
                $confirmation = $this->secret('Confirm the new password');

                if ($password !== $confirmation) {
                    $this->error('Passwords do not match.');
                    return self::FAILURE;
                }
            }
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        try {
            $user->update([
                'password' => Hash::make($password),
            ]);

            $this->info("Password changed successfully for '{$user->name}'.");

            if ($generatedPassword) {
                $this->newLine();
                $this->warn('Generated password: ' . $password);
                $this->info('Make sure to save this password - it will not be shown again.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to change password: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
