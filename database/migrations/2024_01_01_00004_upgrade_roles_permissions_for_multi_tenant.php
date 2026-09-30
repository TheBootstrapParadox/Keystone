<?php

use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;

/**
 * Bring a schema created in single-tenant mode up to the full multi-tenant
 * schema, when multi-tenancy is enabled the first time this migration runs.
 *
 * Migrations only run once: an install that already ran this migration in
 * single-tenant mode upgrades later with `php artisan keystone:enable-multi-tenancy`,
 * which runs the same idempotent steps. Does nothing on a fresh multi-tenant
 * install or when multi-tenancy is disabled.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('keystone.features.multi_tenant', false)) {
            (new MultiTenantSchemaUpgrade)->run();
        }
    }

    /**
     * Reverse the migrations.
     *
     * Switching from multi-tenant back to single-tenant is not supported, and
     * a full rollback drops these tables (and the users column) in the
     * earlier migrations' down() methods, so there is nothing to undo here.
     */
    public function down(): void
    {
        //
    }
};
