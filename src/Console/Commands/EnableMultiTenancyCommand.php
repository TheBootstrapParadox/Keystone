<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnableMultiTenancyCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:enable-multi-tenancy
        {--force : Run without asking for confirmation}';

    protected $description = "Move existing role and permission assignments into their users' tenants";

    public function handle(): int
    {
        if (! $this->isMultiTenantEnabled()) {
            $this->error('Multi-tenancy is not enabled. Set KEYSTONE_MULTI_TENANT=true (keystone.features.multi_tenant) first.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Every assignment stored without a tenant will be moved into its user's tenant, including ones stored that way on purpose. Continue?"
        )) {
            return self::FAILURE;
        }

        try {
            $changed = DB::transaction(fn () => (new MultiTenantSchemaUpgrade)->backfillAssignments());
        } catch (\Exception $e) {
            $this->error("Failed to move assignments into tenants: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($changed === 0) {
            $this->info('Every assignment already has a tenant or belongs to a user without one. Nothing to change.');

            return self::SUCCESS;
        }

        $this->clearPermissionCache();
        $this->info("Moved {$changed} assignment(s) into their users' tenants.");

        return self::SUCCESS;
    }
}
