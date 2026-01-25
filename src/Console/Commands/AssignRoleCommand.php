<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Console\Command;

class AssignRoleCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:assign-role
        {user : The user ID or email}
        {role?* : Role name(s) to assign}
        {--R|role=* : Role(s) to assign (can be used multiple times)}
        {--sync : Replace existing roles instead of adding}
        {--remove : Remove the specified roles instead of adding}';

    protected $description = 'Assign or remove role(s) for a user';

    public function __construct(
        protected AuthorizationServiceInterface $authorizationService,
        protected RoleServiceInterface $roleService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $user = $this->findUser($userIdentifier);

        if (!$user) {
            $this->error("User '{$userIdentifier}' not found.");
            return Command::FAILURE;
        }

        // Gather roles from arguments and options
        $roles = $this->gatherRoles();

        if (empty($roles)) {
            $this->error('No roles specified. Provide role names as arguments or use --role option.');
            return Command::FAILURE;
        }

        // Get current roles for display
        $previousRoles = $this->roleService->getUserRoles($user)->pluck('name')->toArray();

        try {
            if ($this->option('remove')) {
                // Remove specified roles
                foreach ($roles as $role) {
                    $user->removeRole($role);
                }
                $action = 'removed';
            } elseif ($this->option('sync')) {
                // Replace all roles
                $this->authorizationService->assignRolesToUser($user, $roles);
                $action = 'synced';
            } else {
                // Add roles (default behavior)
                foreach ($roles as $role) {
                    $user->assignRole($role);
                }
                $action = 'assigned';
            }

            $this->clearPermissionCache();
            $user->refresh();

            $currentRoles = $this->roleService->getUserRoles($user)->pluck('name')->toArray();

            $this->info("Roles {$action} successfully for user: {$user->email}");

            $this->newLine();
            $this->table(
                ['Property', 'Value'],
                [
                    ['User', $user->email],
                    ['Previous Roles', implode(', ', $previousRoles) ?: '(none)'],
                    [$this->option('remove') ? 'Removed Roles' : 'Specified Roles', implode(', ', $roles)],
                    ['Current Roles', implode(', ', $currentRoles) ?: '(none)'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $action = $this->option('remove') ? 'remove' : 'assign';
            $this->error("Failed to {$action} roles: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Gather roles from arguments and options.
     */
    protected function gatherRoles(): array
    {
        $roles = $this->argument('role') ?? [];

        // From -R / --role options (repeatable)
        if ($roleOptions = $this->option('role')) {
            $roles = array_merge($roles, $roleOptions);
        }

        return array_unique(array_filter($roles));
    }
}
