<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Console\Command;

class MakeRoleCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:make-role
        {name : The name of the role}
        {--guard= : The guard name (defaults to config default)}
        {--permissions= : Comma-separated list of permissions to assign}
        {--P|permission=* : Permission(s) to assign (can be used multiple times)}';

    protected $description = 'Create a new role with optional permissions';

    public function __construct(
        protected RoleServiceInterface $roleService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $guard = $this->resolveGuard();

        if ($guard === null) {
            return Command::FAILURE;
        }

        try {
            $role = $this->roleService->create($name, $guard);

            // Gather permissions from both options
            $permissions = $this->gatherPermissions();

            if (!empty($permissions)) {
                $this->roleService->syncPermissions($role, $permissions);
                $role->refresh();
            }

            $this->clearPermissionCache();

            $this->info("Role '{$role->name}' created successfully.");

            $this->newLine();
            $this->table(
                ['Name', 'Guard', 'Permissions'],
                [[
                    $role->name,
                    $role->guard_name,
                    $role->permissions->pluck('name')->implode(', ') ?: '(none)'
                ]]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create role: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Gather permissions from command options.
     */
    protected function gatherPermissions(): array
    {
        $permissions = [];

        // From --permissions option (comma-separated)
        if ($permissionString = $this->option('permissions')) {
            $permissions = array_merge(
                $permissions,
                array_map('trim', explode(',', $permissionString))
            );
        }

        // From -P / --permission options (repeatable)
        if ($permissionOptions = $this->option('permission')) {
            $permissions = array_merge($permissions, $permissionOptions);
        }

        return array_unique(array_filter($permissions));
    }
}
