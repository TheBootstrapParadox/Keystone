<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use BSPDX\Keystone\Support\PasskeyConfig;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run if multi-tenancy is enabled
        if (!config('keystone.features.multi_tenant', false)) {
            return;
        }

        // Detect if user model uses UUIDs (same logic as permission migration line 24)
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds');

        $columnNames = config('keystone.permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key']; // Will be 'tenant_id'
        $tableNames = config('keystone.permission.table_names');

        // Add tenant_id to model_has_roles if not exists
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamForeignKey, $useUuids) {
            if (!Schema::hasColumn('model_has_roles', $teamForeignKey)) {
                if ($useUuids) {
                    $table->uuid($teamForeignKey)->nullable()->after('model_id');
                } else {
                    $table->unsignedBigInteger($teamForeignKey)->nullable()->after('model_id');
                }
                $table->index($teamForeignKey, 'model_has_roles_tenant_foreign_key_index');
            }
        });

        // Add tenant_id to model_has_permissions if not exists
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamForeignKey, $useUuids) {
            if (!Schema::hasColumn('model_has_permissions', $teamForeignKey)) {
                if ($useUuids) {
                    $table->uuid($teamForeignKey)->nullable()->after('model_id');
                } else {
                    $table->unsignedBigInteger($teamForeignKey)->nullable()->after('model_id');
                }
                $table->index($teamForeignKey, 'model_has_permissions_tenant_foreign_key_index');
            }
        });

        // Backfill tenant_id from user records
        $userTableName = (new $authenticatable)->getTable();

        // Backfill for model_has_roles
        DB::statement("
            UPDATE {$tableNames['model_has_roles']} mhr
            JOIN {$userTableName} u ON mhr.model_id = u.id AND mhr.model_type LIKE '%User'
            SET mhr.{$teamForeignKey} = u.tenant_id
            WHERE u.tenant_id IS NOT NULL
        ");

        // Backfill for model_has_permissions
        DB::statement("
            UPDATE {$tableNames['model_has_permissions']} mhp
            JOIN {$userTableName} u ON mhp.model_id = u.id AND mhp.model_type LIKE '%User'
            SET mhp.{$teamForeignKey} = u.tenant_id
            WHERE u.tenant_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run if multi-tenancy is enabled
        if (!config('keystone.features.multi_tenant', false)) {
            return;
        }

        $columnNames = config('keystone.permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key']; // Will be 'tenant_id'
        $tableNames = config('keystone.permission.table_names');

        // Remove tenant_id from model_has_roles
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamForeignKey) {
            if (Schema::hasColumn('model_has_roles', $teamForeignKey)) {
                $table->dropIndex('model_has_roles_tenant_foreign_key_index');
                $table->dropColumn($teamForeignKey);
            }
        });

        // Remove tenant_id from model_has_permissions
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamForeignKey) {
            if (Schema::hasColumn('model_has_permissions', $teamForeignKey)) {
                $table->dropIndex('model_has_permissions_tenant_foreign_key_index');
                $table->dropColumn($teamForeignKey);
            }
        });
    }
};
