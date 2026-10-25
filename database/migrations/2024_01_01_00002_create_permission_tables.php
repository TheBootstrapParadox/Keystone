<?php

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
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
        // Match the pivots' model_id column to the user model's key type
        $authenticatableClass = config('keystone.user.model') ?? config('auth.providers.users.model')
            ?? throw new LogicException('Keystone could not determine the user model. Set keystone.user.model or auth.providers.users.model.');
        $modelIdType = $this->modelIdType(new $authenticatableClass);

        // Permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            // Global (tenant_id = NULL) or tenant-specific; always NULL in single-tenant mode
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            $table->uuid('tenant_id')->nullable();

            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('title')->nullable()->comment('Display name for UI');
            $table->text('description')->nullable()->comment('Explains permission purpose');
            $table->timestamps();

            // Leads with tenant_id, so it also serves tenant lookups. NULL tenants
            // are distinct in unique keys; the models reject duplicate names.
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        // Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            // Global (tenant_id = NULL) or tenant-specific; always NULL in single-tenant mode
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            $table->uuid('tenant_id')->nullable();

            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('title')->nullable()->comment('Display name for UI');
            $table->text('description')->nullable()->comment('Explains role purpose and scope');
            $table->timestamps();

            // Leads with tenant_id, so it also serves tenant lookups. NULL tenants
            // are distinct in unique keys; the models reject duplicate names.
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        // Model has permissions pivot table
        Schema::create('model_has_permissions', function (Blueprint $table) use ($modelIdType) {
            $table->id(); // Auto-increment primary key

            $table->unsignedBigInteger('permission_id');

            $table->string('model_type');
            match ($modelIdType) {
                'ulid' => $table->ulid('model_id'),
                'uuid' => $table->uuid('model_id'),
                default => $table->unsignedBigInteger('model_id'),
            };
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            // The assigned user's tenant (NULL in single-tenant mode)
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            $table->uuid('tenant_id')->nullable();

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            // Assignments are always hard-deleted, so no soft-delete column
            $table->timestamps();

            // Unique per tenant context. It does not deduplicate rows whose
            // tenant_id is NULL (NULLs are distinct in unique keys).
            $table->unique(['tenant_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_unique');
        });

        // Model has roles pivot table
        Schema::create('model_has_roles', function (Blueprint $table) use ($modelIdType) {
            $table->id(); // Auto-increment primary key

            $table->unsignedBigInteger('role_id');

            $table->string('model_type');
            match ($modelIdType) {
                'ulid' => $table->ulid('model_id'),
                'uuid' => $table->uuid('model_id'),
                default => $table->unsignedBigInteger('model_id'),
            };
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            // The assigned user's tenant (NULL in single-tenant mode)
            // NOTE: tenant_id is always UUID to match users.tenant_id column type
            $table->uuid('tenant_id')->nullable();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            // Assignments are always hard-deleted, so no soft-delete column
            $table->timestamps();

            // Unique per tenant context. It does not deduplicate rows whose
            // tenant_id is NULL (NULLs are distinct in unique keys).
            $table->unique(['tenant_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_unique');
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

            // Assignments are always hard-deleted, so no soft-delete column
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['permission_id', 'role_id'], 'role_has_permissions_unique');
        });
    }

    /**
     * The column type for the pivots' model_id, from the user model's key.
     */
    public function modelIdType(Model $user): string
    {
        $traits = class_uses_recursive($user);

        if (in_array(HasUlids::class, $traits, true)) {
            return 'ulid';
        }

        if (in_array(HasUuids::class, $traits, true) || count($user->uniqueIds()) > 0) {
            return 'uuid';
        }

        return 'integer';
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
