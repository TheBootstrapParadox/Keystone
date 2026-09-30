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
     * (a no-op on a fresh multi-tenant install). Installs that already ran
     * this migration in single-tenant mode upgrade with
     * `php artisan keystone:enable-multi-tenancy` instead.
     */
    public function up(): void
    {
        // Only run if multi-tenancy is enabled
        if (! config('keystone.features.multi_tenant', false)) {
            return;
        }

        (new MultiTenantSchemaUpgrade)->addPivotTenantColumns();
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
