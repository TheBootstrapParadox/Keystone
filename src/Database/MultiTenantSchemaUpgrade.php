<?php

namespace BSPDX\Keystone\Database;

use BSPDX\Keystone\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Brings a schema created in single-tenant mode before Keystone's schema
 * became the same in both modes up to that one schema.
 *
 * Every schema step is idempotent, so running it on a schema that already
 * has the tenant columns and tenant-aware keys changes nothing. Existing
 * roles, permissions, and assignments keep a NULL tenant_id. The schema
 * steps are used by Keystone's migrations; the assignment backfill is used
 * by the keystone:enable-multi-tenancy command.
 *
 * @internal
 */
final class MultiTenantSchemaUpgrade
{
    /**
     * Index name for the users table's tenant_id, shared with the first
     * migration so its rollback recognises the column as Keystone's.
     */
    public const USERS_TENANT_INDEX = 'keystone_users_tenant_id_index';

    /**
     * Unique keys to make tenant-aware: table => [old columns, new name, new columns].
     *
     * @var array<string, array{0: list<string>, 1: string, 2: list<string>}>
     */
    private const UNIQUE_KEYS = [
        'roles' => [['name', 'guard_name'], 'roles_tenant_id_name_guard_name_unique', ['tenant_id', 'name', 'guard_name']],
        'permissions' => [['name', 'guard_name'], 'permissions_tenant_id_name_guard_name_unique', ['tenant_id', 'name', 'guard_name']],
        'model_has_roles' => [['role_id', 'model_id', 'model_type'], 'model_has_roles_tenant_unique', ['tenant_id', 'role_id', 'model_id', 'model_type']],
        'model_has_permissions' => [['permission_id', 'model_id', 'model_type'], 'model_has_permissions_tenant_unique', ['tenant_id', 'permission_id', 'model_id', 'model_type']],
    ];

    /**
     * Run every schema step.
     *
     * @return list<string> a description of each change made (empty when none)
     */
    public function run(): array
    {
        return [
            ...$this->addUsersTenantColumn(),
            ...$this->addRolePermissionTenantColumns(),
            ...$this->addPivotTenantColumns(),
            ...$this->makeUniqueKeysTenantAware(),
        ];
    }

    /**
     * Add tenant_id to the configured user model's table.
     *
     * @return list<string>
     */
    public function addUsersTenantColumn(): array
    {
        $table = $this->userModel()->getTable();

        if (Schema::hasColumn($table, 'tenant_id')) {
            return [];
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->uuid('tenant_id')->nullable()->after('id');
            $blueprint->index('tenant_id', self::USERS_TENANT_INDEX);
        });

        return ["Added {$table}.tenant_id"];
    }

    /**
     * Add tenant_id to roles and permissions (existing rows become global).
     *
     * No index on tenant_id alone: the tenant-aware unique key leads with it.
     *
     * @return list<string>
     */
    public function addRolePermissionTenantColumns(): array
    {
        $changes = [];

        foreach (['roles', 'permissions'] as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('tenant_id')->nullable()->after('id');
            });

            $changes[] = "Added {$table}.tenant_id (existing rows are global)";
        }

        return $changes;
    }

    /**
     * Add tenant_id to the assignment pivots (existing rows keep NULL).
     *
     * No index on tenant_id alone: the tenant-aware unique key leads with it.
     *
     * @return list<string>
     */
    public function addPivotTenantColumns(): array
    {
        $changes = [];

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('tenant_id')->nullable()->after('model_id');
            });

            $changes[] = "Added {$table}.tenant_id";
        }

        return $changes;
    }

    /**
     * Replace each single-tenant unique key with its tenant-aware equivalent.
     *
     * @return list<string>
     *
     * @throws RuntimeException when neither the old nor the new key can be found
     */
    public function makeUniqueKeysTenantAware(): array
    {
        $changes = [];

        foreach (self::UNIQUE_KEYS as $table => [$oldColumns, $newName, $newColumns]) {
            if (Schema::hasIndex($table, $newColumns, 'unique')) {
                continue;
            }

            $old = collect(Schema::getIndexes($table))
                ->first(fn (array $index) => $index['unique'] && $index['columns'] === $oldColumns);

            if ($old === null) {
                throw new RuntimeException(
                    "Keystone could not find the unique key on {$table} (".implode(', ', $oldColumns).') to make it tenant-aware. '.
                    'If it was renamed or removed, add a unique key on ('.implode(', ', $newColumns).') yourself and run this again.'
                );
            }

            $this->keepForeignKeyIndexed($table, $oldColumns[0], $old['name']);

            Schema::table($table, function (Blueprint $blueprint) use ($old, $newColumns, $newName) {
                $blueprint->dropUnique($old['name']);
                $blueprint->unique($newColumns, $newName);
            });

            $changes[] = "Made the {$table} unique key tenant-aware (".implode(', ', $newColumns).')';
        }

        return $changes;
    }

    /**
     * MySQL won't drop the only index that backs a foreign key. When the
     * unique key about to be dropped is the only index leading with a
     * foreign-key column (e.g. role_id on the pivots), index that column first.
     */
    private function keepForeignKeyIndexed(string $table, string $column, string $droppingIndex): void
    {
        $isForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === [$column]);

        $otherwiseIndexed = collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['name'] !== $droppingIndex && ($index['columns'][0] ?? null) === $column);

        if ($isForeignKey && ! $otherwiseIndexed) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column));
        }
    }

    /**
     * Stamp both assignment pivots' NULL-tenant rows with their user's tenant.
     *
     * @return int the number of rows changed
     */
    public function backfillAssignments(): int
    {
        $user = $this->userModel();

        return $this->backfillTenantId('model_has_roles', $user)
            + $this->backfillTenantId('model_has_permissions', $user);
    }

    /**
     * Copy each assigned user's tenant_id onto their pivot rows that have none.
     *
     * A correlated subquery, which SQLite, MySQL/MariaDB, and PostgreSQL all
     * accept. Rows are matched by the user model's morph class (so morph maps
     * work) and key name, never by a model_type pattern.
     */
    public function backfillTenantId(string $pivotTable, Model $user): int
    {
        $userTable = $user->getTable();
        $userKey = $user->getKeyName();

        // Users without a tenant column have no tenant to copy
        if (! Schema::hasColumn($userTable, 'tenant_id')) {
            return 0;
        }

        $tenantOfUser = DB::table($userTable, 'keystone_users')
            ->select('keystone_users.tenant_id')
            ->whereColumn("keystone_users.{$userKey}", "{$pivotTable}.model_id")
            ->limit(1);

        return DB::table($pivotTable)
            ->whereNull('tenant_id')
            ->where('model_type', $user->getMorphClass())
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from($userTable, 'keystone_users')
                ->whereColumn("keystone_users.{$userKey}", "{$pivotTable}.model_id")
                ->whereNotNull('keystone_users.tenant_id'))
            ->update(['tenant_id' => DB::raw('('.$tenantOfUser->toSql().')')]);
    }

    private function userModel(): Model
    {
        return UserModel::instance();
    }
}
