<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Resolve table name dynamically
        $authenticatable = config('auth.providers.users.model', \App\Models\User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Auth preference columns for passwordless login options
            if (!Schema::hasColumn($tableName, 'allow_passkey_login')) {
                $table->boolean('allow_passkey_login')->default(false)
                    ->after('two_factor_confirmed_at')
                    ->comment('Allow passkey as primary authentication');
            }

            if (!Schema::hasColumn($tableName, 'allow_totp_login')) {
                $table->boolean('allow_totp_login')->default(false)
                    ->after('allow_passkey_login')
                    ->comment('Allow TOTP code as primary authentication');
            }

            if (!Schema::hasColumn($tableName, 'require_password')) {
                $table->boolean('require_password')->default(true)
                    ->after('allow_totp_login')
                    ->comment('Whether password is required for login');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        // Resolve table name dynamically
        $authenticatable = config('auth.providers.users.model', \App\Models\User::class);
        $tableName = (new $authenticatable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'require_password')) {
                $table->dropColumn('require_password');
            }
            if (Schema::hasColumn($tableName, 'allow_totp_login')) {
                $table->dropColumn('allow_totp_login');
            }
            if (Schema::hasColumn($tableName, 'allow_passkey_login')) {
                $table->dropColumn('allow_passkey_login');
            }
        });
    }
};
