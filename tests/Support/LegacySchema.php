<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds Keystone's tables as installs created before the schema became
 * the same in both tenancy modes had them.
 */
final class LegacySchema
{
    private const TABLES = ['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'];

    /**
     * The single-tenant schema: no tenant_id anywhere, (name, guard_name)
     * unique keys.
     */
    public static function singleTenant(): void
    {
        self::dropKeystoneTables();
        self::dropUsersTenantColumn();

        foreach (['permissions', 'roles'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('guard_name')->default('web');
                $blueprint->string('title')->nullable();
                $blueprint->text('description')->nullable();
                $blueprint->timestamps();
                $blueprint->unique(['name', 'guard_name']);
            });
        }

        foreach (['model_has_permissions' => ['permission_id', 'permissions'], 'model_has_roles' => ['role_id', 'roles']] as $table => [$key, $related]) {
            Schema::create($table, function (Blueprint $blueprint) use ($table, $key, $related) {
                $blueprint->id();
                $blueprint->unsignedBigInteger($key);
                $blueprint->string('model_type');
                $blueprint->unsignedBigInteger('model_id');
                $blueprint->index(['model_id', 'model_type'], "{$table}_model_id_model_type_index");
                $blueprint->foreign($key)->references('id')->on($related)->onDelete('cascade');
                $blueprint->timestamps();
                $blueprint->softDeletes();
                $blueprint->unique([$key, 'model_id', 'model_type'], "{$table}_unique");
            });
        }

        self::createRoleHasPermissions();
    }

    /**
     * The multi-tenant schema, with the separate tenant_id indexes it used to
     * create next to its tenant-aware unique keys.
     */
    public static function multiTenantWithTenantIndexes(): void
    {
        self::dropKeystoneTables();

        foreach (['permissions', 'roles'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->uuid('tenant_id')->nullable();
                $blueprint->index('tenant_id');
                $blueprint->string('name');
                $blueprint->string('guard_name')->default('web');
                $blueprint->string('title')->nullable();
                $blueprint->text('description')->nullable();
                $blueprint->timestamps();
                $blueprint->unique(['tenant_id', 'name', 'guard_name']);
            });
        }

        foreach (['model_has_permissions' => ['permission_id', 'permissions'], 'model_has_roles' => ['role_id', 'roles']] as $table => [$key, $related]) {
            Schema::create($table, function (Blueprint $blueprint) use ($table, $key, $related) {
                $blueprint->id();
                $blueprint->unsignedBigInteger($key);
                $blueprint->string('model_type');
                $blueprint->unsignedBigInteger('model_id');
                $blueprint->index(['model_id', 'model_type'], "{$table}_model_id_model_type_index");
                $blueprint->uuid('tenant_id')->nullable();
                $blueprint->index('tenant_id', "{$table}_tenant_id_index");
                $blueprint->foreign($key)->references('id')->on($related)->onDelete('cascade');
                $blueprint->timestamps();
                $blueprint->unique(['tenant_id', $key, 'model_id', 'model_type'], "{$table}_unique");
            });
        }

        self::createRoleHasPermissions();
    }

    private static function createRoleHasPermissions(): void
    {
        Schema::create('role_has_permissions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('permission_id');
            $blueprint->unsignedBigInteger('role_id');
            $blueprint->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $blueprint->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $blueprint->timestamps();
            $blueprint->unique(['permission_id', 'role_id'], 'role_has_permissions_unique');
        });
    }

    private static function dropKeystoneTables(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private static function dropUsersTenantColumn(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $blueprint) {
            if (Schema::hasIndex('users', 'keystone_users_tenant_id_index')) {
                $blueprint->dropIndex('keystone_users_tenant_id_index');
            }
            $blueprint->dropColumn('tenant_id');
        });
    }
}
