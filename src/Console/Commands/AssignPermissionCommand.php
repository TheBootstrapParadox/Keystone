<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Console\Command;

class AssignPermissionCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:assign-permission
        {permission?* : Permission name(s) to assign}
        {--P|permission=* : Permission(s) to assign (can be used multiple times)}
        {--to-role= : Assign to a role}
        {--to-user= : Assign directly to a user (ID or email)}
        {--guard= : The guard name}
        {--sync : Replace existing permissions instead of adding}
        {--remove : Remove the specified permissions instead of adding}';

    protected $description = 'Assign permission(s) to a role or user';

    public function __construct(
        protected PermissionServiceInterface $permissionService,
        protected RoleServiceInterface $roleService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $toRole = $this->option('to-role');
        $toUser = $this->option('to-user');

        // Validate target
        if (!$toRole && !$toUser) {
            $this->error('You must specify either --to-role or --to-user.');
            return self::FAILURE;
        }

        if ($toRole && $toUser) {
            $this->error('You cannot specify both --to-role and --to-user. Choose one.');
            return self::FAILURE;
        }

        // Gather permissions
        $permissions = $this->gatherPermissions();

        if (empty($permissions)) {
            $this->error('No permissions specified. Provide permission names as arguments or use --permission option.');
            return self::FAILURE;
        }

        if ($toRole) {
            return $this->assignToRole($toRole, $permissions);
        }

        return $this->assignToUser($toUser, $permissions);
    }

    /**
     * Assign permissions to a role.
     */
    protected function assignToRole(string $roleName, array $permissions): int
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

        try {
            if ($this->option('remove')) {
                $this->permissionService->removeFromRole($role, $permissions);
                $action = 'removed from';
            } elseif ($this->option('sync')) {
                $this->roleService->syncPermissions($role, $permissions);
                $action = 'synced to';
            } else {
                $this->permissionService->assignToRole($role, $permissions);
                $action = 'assigned to';
            }

            $this->clearPermissionCache();
            $role->refresh();

            $currentPermissions = $role->permissions->pluck('name')->toArray();

            $this->info("Permissions {$action} role: {$role->name}");

            $this->newLine();
            $this->table(
                ['Property', 'Value'],
                [
                    ['Role', $role->name],
                    ['Guard', $role->guard_name],
                    ['Previous Permissions', implode(', ', $previousPermissions) ?: '(none)'],
                    [$this->option('remove') ? 'Removed Permissions' : 'Specified Permissions', implode(', ', $permissions)],
                    ['Current Permissions', implode(', ', $currentPermissions) ?: '(none)'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to modify permissions: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Assign permissions directly to a user.
     */
    protected function assignToUser(string $userIdentifier, array $permissions): int
    {
        $user = $this->findUser($userIdentifier);

        if (!$user) {
            $this->error("User '{$userIdentifier}' not found.");
            return self::FAILURE;
        }

        $previousPermissions = $this->permissionService->getUserPermissions($user)->pluck('name')->toArray();

        try {
            if ($this->option('remove')) {
                $this->permissionService->removeFromUser($user, $permissions);
                $action = 'removed from';
            } elseif ($this->option('sync')) {
                $this->permissionService->syncToUser($user, $permissions);
                $action = 'synced to';
            } else {
                $this->permissionService->assignToUser($user, $permissions);
                $action = 'assigned to';
            }

            $this->clearPermissionCache();

            $currentPermissions = $this->permissionService->getUserPermissions($user)->pluck('name')->toArray();
            $allPermissions = $this->permissionService->getAllUserPermissions($user)->pluck('name')->toArray();

            $this->info("Permissions {$action} user: {$user->email}");

            $this->newLine();
            $this->table(
                ['Property', 'Value'],
                [
                    ['User', $user->email],
                    ['Previous Direct Permissions', implode(', ', $previousPermissions) ?: '(none)'],
                    [$this->option('remove') ? 'Removed Permissions' : 'Specified Permissions', implode(', ', $permissions)],
                    ['Current Direct Permissions', implode(', ', $currentPermissions) ?: '(none)'],
                    ['All Permissions (incl. via roles)', count($allPermissions) . ' total'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to modify permissions: {$e->getMessage()}");
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
