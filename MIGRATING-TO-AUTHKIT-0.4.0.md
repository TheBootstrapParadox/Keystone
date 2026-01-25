# Migrating to AuthKit 0.4.0

This guide covers the breaking changes in AuthKit 0.4.0 and how to migrate your existing applications.

## Overview of Breaking Changes

1. **`tenant_id` column is now always a UUID** (was `unsignedBigInteger`)
2. **Permission table migrations auto-detect User model ID type** based on `HasUuids` trait
3. **Removed boilerplate migrations** that conflicted with existing Laravel apps

## Prerequisites

If you want UUID support for the permission tables, your User model must use Laravel's `HasUuids` trait:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use BSPDX\AuthKit\Traits\HasAuthKit;
use BSPDX\AuthKit\Contracts\HasPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    use HasUuids, HasAuthKit;
    // ...
}
```

## Migration Paths

### Fresh Install (No Existing Data)

If starting fresh, just run:

```bash
php artisan migrate:fresh
```

The migrations will automatically detect your User model's ID type.

---

### Existing Install with Data

#### Step 1: Update `tenant_id` Column

If you have existing data with `tenant_id` as bigint, create a migration:

```bash
php artisan make:migration change_tenant_id_to_uuid_on_users_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }
};
```

#### Step 2: Update Permission Tables (If Using UUIDs)

If your User model now uses `HasUuids` and you have existing permission data:

```bash
php artisan make:migration change_model_morph_key_to_uuid_on_permission_tables
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear permission cache
        app('cache')->forget(config('permission.cache.key'));

        // Update model_has_permissions
        $this->convertTableMorphKey('model_has_permissions', 'permission_id');

        // Update model_has_roles
        $this->convertTableMorphKey('model_has_roles', 'role_id');
    }

    private function convertTableMorphKey(string $table, string $pivotKey): void
    {
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        // Drop existing constraints
        Schema::table($table, function (Blueprint $t) use ($table, $pivotKey, $morphKey) {
            $t->dropPrimary();
            $t->dropIndex("{$table}_{$morphKey}_model_type_index");
        });

        // Add new UUID column
        Schema::table($table, function (Blueprint $t) {
            $t->uuid('model_id_new')->nullable();
        });

        // Migrate data: map old bigint IDs to new UUIDs
        // NOTE: This assumes your users table now has a 'uuid' column or uses UUID as primary key
        DB::statement("
            UPDATE {$table}
            SET model_id_new = (
                SELECT id FROM users WHERE users.id = {$table}.{$morphKey}
            )
            WHERE model_type = 'App\\\\Models\\\\User'
        ");

        // Drop old column and rename new one
        Schema::table($table, function (Blueprint $t) use ($morphKey) {
            $t->dropColumn($morphKey);
        });

        Schema::table($table, function (Blueprint $t) use ($morphKey) {
            $t->renameColumn('model_id_new', $morphKey);
        });

        // Recreate constraints
        Schema::table($table, function (Blueprint $t) use ($table, $pivotKey, $morphKey) {
            $t->index([$morphKey, 'model_type'], "{$table}_{$morphKey}_model_type_index");
            $t->primary([$pivotKey, $morphKey, 'model_type'], "{$table}_{$pivotKey}_model_type_primary");
        });
    }

    public function down(): void
    {
        // Reverse migration would require storing original bigint IDs
        // Consider this a one-way migration
        throw new \Exception('This migration cannot be reversed. Restore from backup if needed.');
    }
};
```

## Important Notes

- **Back up your database** before running any migrations
- The permission tables migration auto-detects UUID usage from your User model via `PasskeyConfig::getAuthenticatableModel()`
- If your User model doesn't use `HasUuids`, the permission tables will continue to use `unsignedBigInteger`
- The `tenant_id` column is **always** UUID regardless of User model configuration

## Removed Migrations

The following migrations were removed as they conflict with existing Laravel applications:

- `0001_01_01_00000_create_users_table.php` - Host app provides users table
- `0001_01_01_00001_create_cache_table.php` - Host app configures cache
- `0001_01_01_00002_create_jobs_table.php` - Host app configures queue

If you were relying on these migrations, use Laravel's built-in commands:

```bash
php artisan make:migration create_users_table
php artisan make:cache-table
php artisan make:queue-table
```
