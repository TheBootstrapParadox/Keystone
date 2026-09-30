<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class EnableMultiTenancyCommand extends Command
{
    use ConfirmableTrait, InteractsWithKeystone;

    protected $signature = 'keystone:enable-multi-tenancy
        {--force : Run without confirmation in production}';

    protected $description = 'Upgrade a single-tenant Keystone schema to multi-tenant';

    public function handle(): int
    {
        if (! $this->isMultiTenantEnabled()) {
            $this->error('Multi-tenancy is not enabled. Set KEYSTONE_MULTI_TENANT=true (keystone.features.multi_tenant) first.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $changes = (new MultiTenantSchemaUpgrade)->run();
        } catch (\Exception $e) {
            $this->error("Failed to upgrade the schema: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($changes === []) {
            $this->info('The Keystone schema is already multi-tenant. Nothing to change.');

            return self::SUCCESS;
        }

        foreach ($changes as $change) {
            $this->line("  - {$change}");
        }

        $this->clearPermissionCache();
        $this->info('Keystone schema upgraded to multi-tenant. Existing roles and permissions are global.');

        return self::SUCCESS;
    }
}
