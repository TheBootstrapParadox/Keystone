<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Console\Command;

class UnassignPermissionCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:unassign-permission
        {permission?* : Permission name(s) to remove}
        {--P|permission=* : Permission(s) to remove (can be used multiple times)}
        {--from-role= : Remove from a role}
        {--from-user= : Remove from a user (ID or email)}
        {--guard= : The guard name}
        {--all : Remove all permissions from the target}';

    protected $description = 'Remove permission(s) from a role or user';

    public function __construct(
        protected PermissionServiceInterface $permissionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fromRole = $this->option('from-role');
        $fromUser = $this->option('from-user');

        // Validate target
        if (!$fromRole && !$fromUser) {
            $this->error('You must specify either --from-role or --from-user.');
            return self::FAILURE;
        }

        if ($fromRole && $fromUser) {
            $this->error('You cannot specify both --from-role and --from-user. Choose one.');
            return self::FAILURE;
        }

        if ($fromRole) {
            return $this->removeFromRole($fromRole);
        }

        return $this->removeFromUser($fromUser);
    }

    /**
     * Remove permissions from a role.
     */
    protected function removeFromRole(string $roleName): int
    {
        $guard = $this->resolveGuard();

        if ($guard === null) {
            return self::FAILURE;
        }

        $role = KeystoneRole::where('name', $roleName)
            ->where('guard_name', $guard)
            ->first();

        if (!$role) {
            $this->error("Role '{$roleName}' not found for guard '{$guard}'.");
            return self::FAILURE;
        }

        $previousPermissions = $role->permissions->pluck('name')->toArray();

        if (empty($previousPermissions)) {
            $this->info("Role '{$role->name}' has no permissions to remove.");
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $permissions = $previousPermissions;
        } else {
            $permissions = $this->gatherPermissions();

            if (empty($permissions)) {
                $this->error('No permissions specified. Provide permission names as arguments or use --permission option.');
                return self::FAILURE;
            }
        }

        try {
            $role->revokePermissionTo($permissions);

            $this->clearPermissionCache();
            $role->refresh();

            $currentPermissions = $role->permissions->pluck('name')->toArray();

            $this->info("Permissions removed from role: {$role->name}");

            $this->newLine();
            $this->table(
                ['Property', 'Value'],
                [
                    ['Role', $role->name],
                    ['Guard', $role->guard_name],
                    ['Previous Permissions', implode(', ', $previousPermissions) ?: '(none)'],
                    ['Removed Permissions', implode(', ', $permissions)],
                    ['Current Permissions', implode(', ', $currentPermissions) ?: '(none)'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to remove permissions: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Remove permissions from a user.
     */
    protected function removeFromUser(string $userIdentifier): int
    {
        $user = $this->findUser($userIdentifier);

        if (!$user) {
            $this->error("User '{$userIdentifier}' not found.");
            return self::FAILURE;
        }

        $previousPermissions = $this->permissionService->getUserPermissions($user)->pluck('name')->toArray();

        if (empty($previousPermissions)) {
            $this->info("User '{$user->email}' has no direct permissions to remove.");
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $permissions = $previousPermissions;
        } else {
            $permissions = $this->gatherPermissions();

            if (empty($permissions)) {
                $this->error('No permissions specified. Provide permission names as arguments or use --permission option.');
                return self::FAILURE;
            }
        }

        try {
            $user->revokePermissionTo($permissions);

            $this->clearPermissionCache();

            $currentPermissions = $this->permissionService->getUserPermissions($user)->pluck('name')->toArray();
            $allPermissions = $this->permissionService->getAllUserPermissions($user)->pluck('name')->toArray();

            $this->info("Permissions removed from user: {$user->email}");

            $this->newLine();
            $this->table(
                ['Property', 'Value'],
                [
                    ['User', $user->email],
                    ['Previous Direct Permissions', implode(', ', $previousPermissions) ?: '(none)'],
                    ['Removed Permissions', implode(', ', $permissions)],
                    ['Current Direct Permissions', implode(', ', $currentPermissions) ?: '(none)'],
                    ['All Permissions (incl. via roles)', count($allPermissions) . ' total'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to remove permissions: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Gather permissions from arguments and options.
     */
    protected function gatherPermissions(): array
    {
        $permissions = $this->argument('permission') ?? [];

        // From -P / --permission options (repeatable)
        if ($permissionOptions = $this->option('permission')) {
            $permissions = array_merge($permissions, $permissionOptions);
        }

        return array_unique(array_filter($permissions));
    }
}
