<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run if multi-tenancy is enabled
        if (! config('keystone.features.multi_tenant', false)) {
            return;
        }

        // Resolve the user model class for backfilling tenant_id (same logic as permission migration line 24)
        $authenticatableClass = config('keystone.user.model')
            ?? config('auth.providers.users.model', User::class);
        $authenticatable = new $authenticatableClass;
        $teamForeignKey = 'tenant_id';
        $modelHasRolesTable = 'model_has_roles';
        $modelHasPermissionsTable = 'model_has_permissions';

        // Add tenant_id to model_has_roles if not exists
        Schema::table($modelHasRolesTable, function (Blueprint $table) use ($teamForeignKey) {
            if (! Schema::hasColumn('model_has_roles', $teamForeignKey)) {
                $table->uuid($teamForeignKey)->nullable()->after('model_id');
                $table->index($teamForeignKey, 'model_has_roles_tenant_foreign_key_index');
            }
        });

        // Add tenant_id to model_has_permissions if not exists
        Schema::table($modelHasPermissionsTable, function (Blueprint $table) use ($teamForeignKey) {
            if (! Schema::hasColumn('model_has_permissions', $teamForeignKey)) {
                $table->uuid($teamForeignKey)->nullable()->after('model_id');
                $table->index($teamForeignKey, 'model_has_permissions_tenant_foreign_key_index');
            }
        });

        // Backfill tenant_id from user records
        $userTableName = (new $authenticatable)->getTable();
        $driverName = DB::connection()->getDriverName();

        // Backfill for model_has_roles
        if ($driverName === 'sqlite') {
            // SQLite-compatible syntax using subquery
            DB::statement("
                UPDATE {$modelHasRolesTable}
                SET {$teamForeignKey} = (
                    SELECT tenant_id FROM {$userTableName}
                    WHERE {$userTableName}.id = {$modelHasRolesTable}.model_id
                    AND {$modelHasRolesTable}.model_type LIKE '%User'
                )
                WHERE EXISTS (
                    SELECT 1 FROM {$userTableName}
                    WHERE {$userTableName}.id = {$modelHasRolesTable}.model_id
                    AND {$userTableName}.tenant_id IS NOT NULL
                )
            ");
        } else {
            // MySQL/PostgreSQL syntax with JOIN
            DB::statement("
                UPDATE {$modelHasRolesTable} mhr
                JOIN {$userTableName} u ON mhr.model_id = u.id AND mhr.model_type LIKE '%User'
                SET mhr.{$teamForeignKey} = u.tenant_id
                WHERE u.tenant_id IS NOT NULL
            ");
        }

        // Backfill for model_has_permissions
        if ($driverName === 'sqlite') {
            // SQLite-compatible syntax using subquery
            DB::statement("
                UPDATE {$modelHasPermissionsTable}
                SET {$teamForeignKey} = (
                    SELECT tenant_id FROM {$userTableName}
                    WHERE {$userTableName}.id = {$modelHasPermissionsTable}.model_id
                    AND {$modelHasPermissionsTable}.model_type LIKE '%User'
                )
                WHERE EXISTS (
                    SELECT 1 FROM {$userTableName}
                    WHERE {$userTableName}.id = {$modelHasPermissionsTable}.model_id
                    AND {$userTableName}.tenant_id IS NOT NULL
                )
            ");
        } else {
            // MySQL/PostgreSQL syntax with JOIN
            DB::statement("
                UPDATE {$modelHasPermissionsTable} mhp
                JOIN {$userTableName} u ON mhp.model_id = u.id AND mhp.model_type LIKE '%User'
                SET mhp.{$teamForeignKey} = u.tenant_id
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
        if (! config('keystone.features.multi_tenant', false)) {
            return;
        }

        $teamForeignKey = 'tenant_id';
        $modelHasRolesTable = 'model_has_roles';
        $modelHasPermissionsTable = 'model_has_permissions';

        // Remove tenant_id from model_has_roles
        Schema::table($modelHasRolesTable, function (Blueprint $table) use ($teamForeignKey) {
            if (Schema::hasColumn('model_has_roles', $teamForeignKey)) {
                $table->dropIndex('model_has_roles_tenant_foreign_key_index');
                $table->dropColumn($teamForeignKey);
            }
        });

        // Remove tenant_id from model_has_permissions
        Schema::table($modelHasPermissionsTable, function (Blueprint $table) use ($teamForeignKey) {
            if (Schema::hasColumn('model_has_permissions', $teamForeignKey)) {
                $table->dropIndex('model_has_permissions_tenant_foreign_key_index');
                $table->dropColumn($teamForeignKey);
            }
        });
    }
};
