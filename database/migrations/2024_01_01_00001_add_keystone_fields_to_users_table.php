<?php

use App\Models\User;
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
                $table->index('tenant_id');
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

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
