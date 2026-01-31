<?php

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
        // Only create if using KeystoneUser
        $userModel = config('keystone.user.model');
        if ($userModel !== \BSPDX\Keystone\Models\KeystoneUser::class) {
            return;
        }

        $tableName = config('keystone.user.table_name', 'users');
        $useUuids = config('keystone.user.primary_key_type') === 'uuid';

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($useUuids) {
            if ($useUuids) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }

            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if using KeystoneUser
        $userModel = config('keystone.user.model');
        if ($userModel !== \BSPDX\Keystone\Models\KeystoneUser::class) {
            return;
        }

        $tableName = config('keystone.user.table_name', 'users');

        Schema::dropIfExists($tableName);
    }
};
