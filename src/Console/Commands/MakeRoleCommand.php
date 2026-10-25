<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Console\Commands\Concerns\ResolvesTenantOption;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Console\Command;

class MakeRoleCommand extends Command
{
    use InteractsWithKeystone, ResolvesTenantOption;

    protected $signature = 'keystone:make-role
        {name : The name of the role}
        {--guard= : The guard name (defaults to config default)}
        {--tenant= : Tenant UUID to create the role in (multi-tenant only; global when omitted)}
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

        $tenant = $this->resolveTenant();

        if ($guard === null || $tenant === false) {
            return self::FAILURE;
        }

        try {
            $role = $tenant === null
                ? $this->roleService->create($name, $guard)
                : KeystoneRole::create(['name' => $name, 'guard_name' => $guard, 'tenant_id' => $tenant]);

            // Gather permissions from both options
            $permissions = $this->gatherPermissions();

            if (! empty($permissions)) {
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
                    $role->permissions->pluck('name')->implode(', ') ?: '(none)',
                ]]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create role: {$e->getMessage()}");

            return self::FAILURE;
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
