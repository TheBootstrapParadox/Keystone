<?php

use App\Models\User;
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
        $authenticatable = config('keystone.user.model') ?? config('auth.providers.users.model', User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Multi-tenancy support (only if enabled in features)
            // NOTE: tenant_id is always UUID, regardless of the user model's own primary key type
            if (config('keystone.features.multi_tenant', false) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                // Named so down() can tell a Keystone-added column from the app's own
                $table->index('tenant_id', MultiTenantSchemaUpgrade::USERS_TENANT_INDEX);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Resolve table name dynamically
        $authenticatable = config('keystone.user.model') ?? config('auth.providers.users.model', User::class);
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
