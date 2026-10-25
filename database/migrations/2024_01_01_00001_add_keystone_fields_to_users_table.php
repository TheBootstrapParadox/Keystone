<?php

use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Resolve table name dynamically
        $authenticatable = config('keystone.user.model') ?? config('auth.providers.users.model')
            ?? throw new LogicException('Keystone could not determine the user model. Set keystone.user.model or auth.providers.users.model.');
        $tableName = (new $authenticatable)->getTable();

        // Added whether or not multi-tenancy is enabled, so turning it on or off
        // never needs a schema change (the column is NULL in single-tenant mode)
        if (Schema::hasColumn($tableName, 'tenant_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            // NOTE: tenant_id is always UUID, regardless of the user model's own primary key type.
            // No column position: the users table may not have an `id` column
            $table->uuid('tenant_id')->nullable();
            // Named so down() can tell a Keystone-added column from the app's own
            $table->index('tenant_id', MultiTenantSchemaUpgrade::USERS_TENANT_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Resolve table name dynamically
        $authenticatable = config('keystone.user.model') ?? config('auth.providers.users.model')
            ?? throw new LogicException('Keystone could not determine the user model. Set keystone.user.model or auth.providers.users.model.');
        $tableName = (new $authenticatable)->getTable();

        // Only drop a tenant_id this migration added (it carries our index).
        // An older install's default-named index is ambiguous, so the column
        // is left alone rather than risk dropping the application's own data.
        if (! Schema::hasIndex($tableName, MultiTenantSchemaUpgrade::USERS_TENANT_INDEX)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(MultiTenantSchemaUpgrade::USERS_TENANT_INDEX);
            $table->dropColumn('tenant_id');
        });
    }
};
