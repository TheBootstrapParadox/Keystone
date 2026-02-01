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
     *
     * This migration adds tenant_id to pivot tables if they don't already have it.
     * This is useful when upgrading from non-multi-tenant to multi-tenant setup.
     */
    public function up(): void
    {
        // Only run if multi-tenancy is enabled
        if (!config('keystone.features.multi_tenant', false)) {
            return;
        }

        // Detect if user model uses UUIDs
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds');
        $userTable = (new $authenticatableClass)->getTable();

        // Add tenant_id to model_has_roles if not exists
        if (!Schema::hasColumn('model_has_roles', 'tenant_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) use ($useUuids) {
                if ($useUuids) {
                    $table->uuid('tenant_id')->nullable()->after('model_id');
                } else {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('model_id');
                }
                $table->index('tenant_id', 'model_has_roles_tenant_id_index');
            });

            // Backfill tenant_id from user records
            $this->backfillTenantId('model_has_roles', $userTable);
        }

        // Add tenant_id to model_has_permissions if not exists
        if (!Schema::hasColumn('model_has_permissions', 'tenant_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) use ($useUuids) {
                if ($useUuids) {
                    $table->uuid('tenant_id')->nullable()->after('model_id');
                } else {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('model_id');
                }
                $table->index('tenant_id', 'model_has_permissions_tenant_id_index');
            });

            // Backfill tenant_id from user records
            $this->backfillTenantId('model_has_permissions', $userTable);
        }
    }

    /**
     * Backfill tenant_id in pivot tables from user records.
     *
     * @param string $pivotTable
     * @param string $userTable
     * @return void
     */
    protected function backfillTenantId(string $pivotTable, string $userTable): void
    {
        $driverName = DB::connection()->getDriverName();

        if ($driverName === 'sqlite') {
            // SQLite-compatible syntax using subquery
            DB::statement("
                UPDATE {$pivotTable}
                SET tenant_id = (
                    SELECT tenant_id FROM {$userTable}
                    WHERE {$userTable}.id = {$pivotTable}.model_id
                    AND {$pivotTable}.model_type LIKE '%User'
                )
                WHERE EXISTS (
                    SELECT 1 FROM {$userTable}
                    WHERE {$userTable}.id = {$pivotTable}.model_id
                    AND {$userTable}.tenant_id IS NOT NULL
                )
            ");
        } else {
            // MySQL/PostgreSQL syntax with JOIN
            DB::statement("
                UPDATE {$pivotTable} pivot
                JOIN {$userTable} u ON pivot.model_id = u.id AND pivot.model_type LIKE '%User'
                SET pivot.tenant_id = u.tenant_id
                WHERE u.tenant_id IS NOT NULL
            ");
        }
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

        // Remove tenant_id from model_has_roles
        if (Schema::hasColumn('model_has_roles', 'tenant_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->dropIndex('model_has_roles_tenant_id_index');
                $table->dropColumn('tenant_id');
            });
        }

        // Remove tenant_id from model_has_permissions
        if (Schema::hasColumn('model_has_permissions', 'tenant_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->dropIndex('model_has_permissions_tenant_id_index');
                $table->dropColumn('tenant_id');
            });
        }
    }
};
