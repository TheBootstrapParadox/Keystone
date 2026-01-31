<?php

namespace BSPDX\Keystone\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeUserCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:make-user
        {--name= : The user\'s name}
        {--email= : The user\'s email address}
        {--password= : The user\'s password (will prompt if not provided)}
        {--role=* : Role(s) to assign to the user}
        {--verified : Mark email as verified}
        {--super-admin : Assign the super-admin role}';

    protected $description = 'Create a new user for development';

    public function __construct(
        protected AuthorizationServiceInterface $authorizationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Production warning
        if (app()->environment('production')) {
            if (!$this->confirm('You are in production. Are you sure you want to create a user via CLI?')) {
                return self::SUCCESS;
            }
        }

        // Gather user details
        $name = $this->option('name') ?? $this->ask('What is the user\'s name?');
        $email = $this->option('email') ?? $this->ask('What is the user\'s email?');

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return self::FAILURE;
        }

        // Check for existing user
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email '{$email}' already exists.");
            return self::FAILURE;
        }

        // Get password
        $password = $this->option('password');
        $generatedPassword = false;

        if (!$password) {
            if ($this->confirm('Generate a random password?', true)) {
                $password = Str::random(16);
                $generatedPassword = true;
            } else {
                $password = $this->secret('Enter the password');
            }
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        try {
            // Create the user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => $this->option('verified') ? now() : null,
            ]);

            // Gather roles
            $roles = $this->gatherRoles();

            if (!empty($roles)) {
                $this->authorizationService->assignRolesToUser($user, $roles);
                $user->refresh();
            }

            $this->clearPermissionCache();

            $this->info("User '{$user->name}' created successfully.");

            // Display summary
            $this->newLine();
            $this->table(
                ['ID', 'Name', 'Email', 'Roles', 'Verified'],
                [[
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->roles->pluck('name')->implode(', ') ?: '(none)',
                    $user->email_verified_at ? 'Yes' : 'No',
                ]]
            );

            // Show generated password
            if ($generatedPassword) {
                $this->newLine();
                $this->warn('Generated password: ' . $password);
                $this->info('Make sure to save this password - it will not be shown again.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create user: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Gather roles from command options.
     */
    protected function gatherRoles(): array
    {
        $roles = $this->option('role');

        // Add super-admin if requested
        if ($this->option('super-admin')) {
            $roles[] = $this->getSuperAdminRole();
        }

        // Add default role if configured and no roles specified
        if (empty($roles) && $defaultRole = $this->getDefaultRole()) {
            $roles[] = $defaultRole;
        }

        return array_unique(array_filter($roles));
    }
}
