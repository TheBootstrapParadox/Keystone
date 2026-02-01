<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Console\Command;

class UnassignRoleCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:unassign-role
        {user : The user ID or email}
        {role?* : Role name(s) to remove}
        {--R|role=* : Role(s) to remove (can be used multiple times)}
        {--all : Remove all roles from the user}';

    protected $description = 'Remove role(s) from a user';

    public function __construct(
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
            return self::FAILURE;
        }

        $previousRoles = $this->roleService->getUserRoles($user)->pluck('name')->toArray();

        if (empty($previousRoles)) {
            $this->info("User '{$user->email}' has no roles to remove.");
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $roles = $previousRoles;
        } else {
            $roles = $this->gatherRoles();

            if (empty($roles)) {
                $this->error('No roles specified. Provide role names as arguments or use --role option.');
                return self::FAILURE;
            }
        }

        try {
            $this->roleService->removeFromUser($user, $roles);

            $this->clearPermissionCache();
            $user->refresh();

            $currentRoles = $this->roleService->getUserRoles($user)->pluck('name')->toArray();

            $this->info("Roles removed successfully from user: {$user->email}");

            $this->newLine();
            $this->table(
                ['Property', 'Value'],
                [
                    ['User', $user->email],
                    ['Previous Roles', implode(', ', $previousRoles) ?: '(none)'],
                    ['Removed Roles', implode(', ', $roles)],
                    ['Current Roles', implode(', ', $currentRoles) ?: '(none)'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to remove roles: {$e->getMessage()}");
            return self::FAILURE;
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
