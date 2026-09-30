<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Console\Commands\Concerns\ResolvesTenantOption;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Console\Command;

class MakePermissionCommand extends Command
{
    use InteractsWithKeystone, ResolvesTenantOption;

    protected $signature = 'keystone:make-permission
        {name : The name of the permission}
        {--guard= : The guard name (defaults to config default)}
        {--tenant= : Tenant UUID to create the permission in (multi-tenant only; global when omitted)}';

    protected $description = 'Create a new permission';

    public function __construct(
        protected PermissionServiceInterface $permissionService
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
            $permission = $tenant === null
                ? $this->permissionService->create($name, $guard)
                : KeystonePermission::create(['name' => $name, 'guard_name' => $guard, 'tenant_id' => $tenant]);

            $this->clearPermissionCache();

            $this->info("Permission '{$permission->name}' created successfully.");

            $this->newLine();
            $this->table(
                ['Name', 'Guard'],
                [[$permission->name, $permission->guard_name]]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create permission: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
