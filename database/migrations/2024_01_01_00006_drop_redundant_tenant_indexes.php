<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the index on tenant_id alone from Keystone's four tenant-aware
 * tables. Each table's unique key leads with tenant_id and serves the same
 * lookups. The users table's tenant_id index is not touched.
 */
return new class extends Migration
{
    /**
     * The tables whose redundant tenant_id index is dropped.
     *
     * @var list<string>
     */
    private array $tables = ['roles', 'permissions', 'model_has_roles', 'model_has_permissions'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            $indexes = collect(Schema::getIndexes($table));

            $coveredByUniqueKey = $indexes->contains(
                fn (array $index) => $index['unique'] && ($index['columns'][0] ?? null) === 'tenant_id'
            );

            if (! $coveredByUniqueKey) {
                continue;
            }

            // Found by columns, not name, so default, explicit, and renamed indexes all match
            $redundant = $indexes->filter(
                fn (array $index) => ! $index['unique'] && ! $index['primary'] && $index['columns'] === ['tenant_id']
            );

            foreach ($redundant as $index) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index['name']));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id') || Schema::hasIndex($table, ['tenant_id'])) {
                continue;
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('tenant_id', "{$table}_tenant_id_index"));
        }
    }
};
