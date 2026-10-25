<?php

use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;

/**
 * Bring a schema created in single-tenant mode up to the tenant-aware schema.
 *
 * Runs whatever the multi-tenant setting is, and does nothing on a schema
 * that already has the tenant columns and keys (every fresh install).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new MultiTenantSchemaUpgrade)->run();
    }

    /**
     * Reverse the migrations.
     *
     * A full rollback drops these tables (and the users column) in the
     * earlier migrations' down() methods, so there is nothing to undo here.
     */
    public function down(): void
    {
        //
    }
};
