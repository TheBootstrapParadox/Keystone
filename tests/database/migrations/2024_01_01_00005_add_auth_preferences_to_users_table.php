<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // Auth preference columns for passwordless login options
            $table->boolean('allow_passkey_login')->default(false)
                ->after('two_factor_confirmed_at')
                ->comment('Allow passkey as primary authentication');

            $table->boolean('allow_totp_login')->default(false)
                ->after('allow_passkey_login')
                ->comment('Allow TOTP code as primary authentication');

            $table->boolean('require_password')->default(true)
                ->after('allow_totp_login')
                ->comment('Whether password is required for login');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'allow_passkey_login',
                'allow_totp_login',
                'require_password',
            ]);
        });
    }
};
