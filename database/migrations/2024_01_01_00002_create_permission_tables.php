<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use BSPDX\Keystone\Support\PasskeyConfig;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Detect if the authenticatable model uses UUIDs by checking for the HasUuids trait
        $authenticatableClass = PasskeyConfig::getAuthenticatableModel();
        $authenticatable = new $authenticatableClass;
        $useUuids = method_exists($authenticatable, 'uniqueIds') && count($authenticatable->uniqueIds()) > 0;

        // Permissions table
        Schema::create('permissions', function (Blueprint $table) use ($useUuids) {
            $table->id();

            // Multi-tenancy support (only if enabled in features)
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            if (config('keystone.features.multi_tenant', false)) {
                $table->uuid('tenant_id')->nullable();
                $table->index('tenant_id');
            }

            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('title')->nullable()->comment('Display name for UI');
            $table->text('description')->nullable()->comment('Explains permission purpose');
            $table->timestamps();

            // Unique constraint includes tenant_id when multi-tenant is enabled
            if (config('keystone.features.multi_tenant', false)) {
                $table->unique(['tenant_id', 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        // Roles table
        Schema::create('roles', function (Blueprint $table) use ($useUuids) {
            $table->id();

            // Multi-tenancy support: Roles can be global (tenant_id = NULL) or tenant-specific
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            if (config('keystone.features.multi_tenant', false)) {
                $table->uuid('tenant_id')->nullable();
                $table->index('tenant_id');
            }

            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('title')->nullable()->comment('Display name for UI');
            $table->text('description')->nullable()->comment('Explains role purpose and scope');
            $table->timestamps();

            // Unique constraint includes tenant_id when multi-tenant is enabled
            if (config('keystone.features.multi_tenant', false)) {
                $table->unique(['tenant_id', 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        // Model has permissions pivot table
        Schema::create('model_has_permissions', function (Blueprint $table) use ($useUuids) {
            $table->id(); // Auto-increment primary key

            $table->unsignedBigInteger('permission_id');

            $table->string('model_type');
            if ($useUuids) {
                $table->uuid('model_id');
            } else {
                $table->unsignedBigInteger('model_id');
            }
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            // Multi-tenancy support in pivot table
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            if (config('keystone.features.multi_tenant', false)) {
                $table->uuid('tenant_id')->nullable();
                $table->index('tenant_id');
            }

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            // Audit trail: timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint to prevent duplicates (handles NULL tenant_id correctly)
            if (config('keystone.features.multi_tenant', false)) {
                $table->unique(['tenant_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_unique');
            } else {
                $table->unique(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_unique');
            }
        });

        // Model has roles pivot table
        Schema::create('model_has_roles', function (Blueprint $table) use ($useUuids) {
            $table->id(); // Auto-increment primary key

            $table->unsignedBigInteger('role_id');

            $table->string('model_type');
            if ($useUuids) {
                $table->uuid('model_id');
            } else {
                $table->unsignedBigInteger('model_id');
            }
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            // Multi-tenancy support in pivot table
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            if (config('keystone.features.multi_tenant', false)) {
                $table->uuid('tenant_id')->nullable();
                $table->index('tenant_id');
            }

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            // Audit trail: timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint to prevent duplicates (handles NULL tenant_id correctly)
            if (config('keystone.features.multi_tenant', false)) {
                $table->unique(['tenant_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_unique');
            } else {
                $table->unique(['role_id', 'model_id', 'model_type'], 'model_has_roles_unique');
            }
        });

        // Role has permissions pivot table
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key

            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            // Audit trail: timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint to prevent duplicates
            $table->unique(['permission_id', 'role_id'], 'role_has_permissions_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
