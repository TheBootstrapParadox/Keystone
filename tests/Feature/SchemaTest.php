<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Database\MultiTenantSchemaUpgrade;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\UlidUser;
use Tests\Fixtures\UuidUser;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    #[Test]
    public function fresh_installs_have_no_soft_delete_columns_on_pivots()
    {
        foreach (['model_has_roles', 'model_has_permissions', 'role_has_permissions'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'), "{$table}.deleted_at should not exist");
        }
    }

    #[Test]
    public function role_permission_rows_have_timestamps()
    {
        KeystonePermission::create(['name' => 'edit-posts']);
        $role = KeystoneRole::create(['name' => 'editor'])->givePermissionTo('edit-posts');

        $row = DB::table('role_has_permissions')->where('role_id', $role->id)->first();

        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }

    #[Test]
    public function the_pivot_model_id_type_follows_the_user_models_key()
    {
        $migration = require base_path('database/migrations/2024_01_01_00002_create_permission_tables.php');

        $this->assertSame('ulid', $migration->modelIdType(new UlidUser));
        $this->assertSame('uuid', $migration->modelIdType(new UuidUser));
        $this->assertSame('integer', $migration->modelIdType(new User));
    }

    #[Test]
    #[Group('ddl')]
    public function rolling_back_removes_a_tenant_column_keystone_added()
    {
        $this->requireTenantSchema();
        $migration = require base_path('database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
    }

    #[Test]
    #[Group('ddl')]
    public function rolling_back_keeps_a_tenant_column_the_app_already_had()
    {
        $this->requireTenantSchema();
        $migration = require base_path('database/migrations/2024_01_01_00001_add_keystone_fields_to_users_table.php');
        $migration->down();

        // The app's own tenant_id, created before Keystone ran
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->index();
        });
        $user = User::factory()->create(['tenant_id' => '019c17d6-1a87-71be-a6a4-718da52579e9']);

        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('users', 'tenant_id'));
        $this->assertSame('019c17d6-1a87-71be-a6a4-718da52579e9', DB::table('users')->where('id', $user->id)->value('tenant_id'));
    }

    #[Test]
    public function the_pivot_backfill_matches_the_user_models_morph_class()
    {
        $this->requireTenantSchema();
        Relation::morphMap(['member' => User::class]);

        try {
            KeystoneRole::create(['name' => 'editor']);
            $user = User::factory()->create(['tenant_id' => '019c17d6-1a87-71be-a6a4-718da52579e9']);
            $user->assignRole('editor');
            DB::table('model_has_roles')->update(['tenant_id' => null]);
            // A row for another model type sharing the same model_id
            DB::table('model_has_roles')->insert([
                'role_id' => KeystoneRole::create(['name' => 'other'])->id,
                'model_type' => 'team',
                'model_id' => $user->id,
            ]);

            (new MultiTenantSchemaUpgrade)->backfillTenantId('model_has_roles', new User);

            $this->assertSame('019c17d6-1a87-71be-a6a4-718da52579e9', DB::table('model_has_roles')->where('model_type', 'member')->value('tenant_id'));
            $this->assertNull(DB::table('model_has_roles')->where('model_type', 'team')->value('tenant_id'));
        } finally {
            Relation::morphMap([], false);
        }
    }

    #[Test]
    #[Group('ddl')]
    public function a_ulid_keyed_user_can_be_assigned_roles()
    {
        Schema::create('ulid_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->timestamps();
        });
        config(['keystone.user.model' => UlidUser::class]);

        $migration = require base_path('database/migrations/2024_01_01_00002_create_permission_tables.php');
        $migration->down();
        $migration->up();

        KeystoneRole::create(['name' => 'editor']);
        $user = UlidUser::create();
        $user->assignRole('editor');

        $this->assertTrue($user->fresh()->hasRole('editor'));
        $this->assertSame($user->id, DB::table('model_has_roles')->value('model_id'));
    }

    private function requireTenantSchema(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            $this->markTestSkipped('Requires the multi-tenant schema.');
        }
    }
}
