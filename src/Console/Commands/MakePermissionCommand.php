<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Console\Command;

class MakePermissionCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:make-permission
        {name : The name of the permission}
        {--guard= : The guard name (defaults to config default)}';

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

        if ($guard === null) {
            return self::FAILURE;
        }

        try {
            $permission = $this->permissionService->create($name, $guard);

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
