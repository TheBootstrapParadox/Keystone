<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use BSPDX\Keystone\Support\PasskeyConfig;

return new class extends Migration
{
    public function up()
    {
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $authenticatableTableName = $authenticatable->getTable();

        // Detect if user model uses UUIDs
        $useUuids = method_exists($authenticatable, 'uniqueIds') && count($authenticatable->uniqueIds()) > 0;

        Schema::create('passkeys', function (Blueprint $table) use ($authenticatableTableName, $useUuids) {
            $table->id();

            // Use UUID or bigInteger depending on user model
            if ($useUuids) {
                $table->uuid('authenticatable_id');
            } else {
                $table->unsignedBigInteger('authenticatable_id');
            }

            $table->foreign('authenticatable_id', 'passkeys_authenticatable_fk')
                ->references('id')
                ->on($authenticatableTableName)
                ->cascadeOnDelete();

            $table->text('name');
            $table->text('credential_id');
            $table->json('data');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('passkeys');
    }
};
