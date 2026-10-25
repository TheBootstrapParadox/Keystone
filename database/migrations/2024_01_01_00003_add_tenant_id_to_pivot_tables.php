<?php

use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds tenant_id to the assignment pivots if they don't already have it
     * (a no-op on a fresh install, where 00002 creates it).
     */
    public function up(): void
    {
        (new MultiTenantSchemaUpgrade)->addPivotTenantColumns();
    }

    /**
     * Reverse the migrations.
     *
     * Drops a pivot tenant_id only when no unique key uses it; otherwise the
     * column came from 00002 and goes with the table in 00002's down().
     */
    public function down(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $inUniqueKey = collect(Schema::getIndexes($table))
                ->contains(fn (array $index) => $index['unique'] && in_array('tenant_id', $index['columns'], true));

            if ($inUniqueKey) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasIndex($table, "{$table}_tenant_id_index")) {
                    $blueprint->dropIndex("{$table}_tenant_id_index");
                }
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
