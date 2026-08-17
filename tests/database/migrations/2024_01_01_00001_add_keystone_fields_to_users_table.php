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
        $authenticatable = config('keystone.user.model') ?? config('auth.providers.users.model', User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $authenticatable = config('keystone.user.model') ?? config('auth.providers.users.model', User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['tenant_id']);
        });
    }
};
