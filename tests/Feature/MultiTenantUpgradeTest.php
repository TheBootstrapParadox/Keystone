<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LegacySchema;
use Tests\TestCase;

/**
 * Every install converges on one schema, whatever the multi-tenant setting:
 * 00005 upgrades schemas created in single-tenant mode, and 00006 drops the
 * redundant tenant_id indexes.
 */
#[Group('ddl')]
class MultiTenantUpgradeTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    private const TENANT_TABLES = ['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions'];

    #[Test]
    public function fresh_installs_have_the_same_schema_in_both_modes()
    {
        $schemaIn = function (bool $multiTenant): array {
            config(['keystone.features.multi_tenant' => $multiTenant]);
            $this->migration('00002_create_permission_tables')->down();
            $this->migration('00002_create_permission_tables')->up();

            return collect(['roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'])
                ->mapWithKeys(fn ($table) => [$table => [Schema::getColumns($table), Schema::getIndexes($table)]])
                ->all();
        };

        $this->assertEquals($schemaIn(false), $schemaIn(true));
    }

    #[Test]
    public function the_uniform_schema_migration_upgrades_a_single_tenant_install()
    {
        $user = $this->singleTenantInstallWithData();

        $this->migration('00005_make_tenant_schema_uniform')->up();

        $this->assertUpgraded($user);
    }

    #[Test]
    public function the_older_migrations_upgrade_the_same_way()
    {
        $user = $this->singleTenantInstallWithData();

        $this->migration('00003_add_tenant_id_to_pivot_tables')->up();
        $this->migration('00004_upgrade_roles_permissions_for_multi_tenant')->up();

        $this->assertUpgraded($user);
    }

    #[Test]
    public function the_upgrade_runs_with_multi_tenancy_on_too()
    {
        $user = $this->singleTenantInstallWithData();
        config(['keystone.features.multi_tenant' => true]);

        $this->migration('00005_make_tenant_schema_uniform')->up();

        $this->assertUpgraded($user);
    }

    #[Test]
    public function the_uniform_schema_migration_changes_nothing_on_a_uniform_schema()
    {
        $before = $this->schemaSnapshot();

        $this->migration('00005_make_tenant_schema_uniform')->up();
        $this->migration('00004_upgrade_roles_permissions_for_multi_tenant')->up();

        $this->assertEquals($before, $this->schemaSnapshot());
    }

    // ------------------------------------------------------------------
    // Redundant tenant indexes
    // ------------------------------------------------------------------

    #[Test]
    public function fresh_installs_have_no_index_on_tenant_id_alone()
    {
        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertFalse(Schema::hasIndex($table, ['tenant_id']), "{$table} should have no tenant_id index");
        }
        $this->assertTrue(Schema::hasIndex('users', 'keystone_users_tenant_id_index'));
    }

    #[Test]
    public function the_redundant_tenant_indexes_are_dropped()
    {
        LegacySchema::multiTenantWithTenantIndexes();

        $this->migration('00006_drop_redundant_tenant_indexes')->up();

        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertFalse(Schema::hasIndex($table, ['tenant_id']), "{$table} should have no tenant_id index");
            $this->assertTrue(
                collect(Schema::getIndexes($table))->contains(fn ($index) => $index['unique'] && $index['columns'][0] === 'tenant_id'),
                "{$table} should keep its tenant-leading unique key"
            );
        }
        $this->assertTrue(Schema::hasIndex('users', 'keystone_users_tenant_id_index'));
    }

    #[Test]
    public function a_tenant_index_without_a_covering_unique_key_is_kept()
    {
        LegacySchema::multiTenantWithTenantIndexes();
        Schema::table('roles', function ($table) {
            $table->dropUnique('roles_tenant_id_name_guard_name_unique');
            $table->unique(['name', 'guard_name', 'tenant_id']);
        });

        $this->migration('00006_drop_redundant_tenant_indexes')->up();

        $this->assertTrue(Schema::hasIndex('roles', ['tenant_id']));
        $this->assertFalse(Schema::hasIndex('permissions', ['tenant_id']));
    }

    #[Test]
    public function rolling_back_restores_the_tenant_indexes()
    {
        LegacySchema::multiTenantWithTenantIndexes();
        $migration = $this->migration('00006_drop_redundant_tenant_indexes');
        $migration->up();

        $migration->down();

        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertTrue(Schema::hasIndex($table, ['tenant_id']), "{$table} should have its tenant_id index back");
        }
    }

    #[Test]
    public function every_keystone_migration_rolls_back()
    {
        foreach (['00006_drop_redundant_tenant_indexes', '00005_make_tenant_schema_uniform', '00004_upgrade_roles_permissions_for_multi_tenant',
            '00003_add_tenant_id_to_pivot_tables', '00002_create_permission_tables', '00001_add_keystone_fields_to_users_table'] as $name) {
            $this->migration($name)->down();
        }

        $this->assertFalse(Schema::hasTable('roles'));
        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
    }

    // ------------------------------------------------------------------
    // keystone:enable-multi-tenancy
    // ------------------------------------------------------------------

    #[Test]
    public function enabling_stamps_tenantless_assignments_with_the_users_tenant()
    {
        KeystoneRole::create(['name' => 'editor']);
        $user = User::factory()->create();
        $user->assignRole('editor');
        $loner = User::factory()->create();
        $loner->assignRole('editor');
        DB::table('users')->where('id', $user->id)->update(['tenant_id' => self::TENANT_A]);
        config(['keystone.features.multi_tenant' => true]);

        $this->artisan('keystone:enable-multi-tenancy', ['--force' => true])
            ->expectsOutputToContain('Moved 1 assignment')
            ->assertSuccessful();

        $this->assertSame(self::TENANT_A, DB::table('model_has_roles')->where('model_id', $user->id)->value('tenant_id'));
        $this->assertNull(DB::table('model_has_roles')->where('model_id', $loner->id)->value('tenant_id'));
        $this->assertTrue($user->fresh()->hasRole('editor'));

        $this->artisan('keystone:enable-multi-tenancy', ['--force' => true])
            ->expectsOutputToContain('Nothing to change')
            ->assertSuccessful();
    }

    #[Test]
    public function enabling_asks_for_confirmation()
    {
        config(['keystone.features.multi_tenant' => true]);

        $this->artisan('keystone:enable-multi-tenancy')
            ->expectsConfirmation("Every assignment stored without a tenant will be moved into its user's tenant, including ones stored that way on purpose. Continue?", 'no')
            ->assertFailed();
    }

    #[Test]
    public function enabling_fails_when_multi_tenancy_is_off()
    {
        config(['keystone.features.multi_tenant' => false]);
        KeystoneRole::create(['name' => 'editor']);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        DB::table('model_has_roles')->insert(['role_id' => KeystoneRole::first()->id, 'model_type' => $user->getMorphClass(), 'model_id' => $user->id]);

        $this->artisan('keystone:enable-multi-tenancy', ['--force' => true])
            ->expectsOutputToContain('Multi-tenancy is not enabled')
            ->assertFailed();

        $this->assertNull(DB::table('model_has_roles')->value('tenant_id'));
    }

    // ------------------------------------------------------------------
    // keystone:disable-multi-tenancy
    // ------------------------------------------------------------------

    #[Test]
    public function disabling_clears_every_tenant_and_leaves_users_alone()
    {
        config(['keystone.features.multi_tenant' => true]);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        KeystonePermission::create(['name' => 'audit', 'tenant_id' => self::TENANT_A]);
        KeystoneRole::create(['name' => 'auditor', 'tenant_id' => self::TENANT_A])->givePermissionTo('audit');
        $user->assignRole('auditor')->givePermissionTo('audit');

        $this->artisan('keystone:disable-multi-tenancy', ['--force' => true])
            ->expectsOutputToContain('KEYSTONE_MULTI_TENANT=false')
            ->assertSuccessful();

        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNotNull('tenant_id')->count(), "{$table} should have no tenant");
        }
        $this->assertSame(self::TENANT_A, DB::table('users')->where('id', $user->id)->value('tenant_id'));

        config(['keystone.features.multi_tenant' => false]);
        $this->assertTrue($user->fresh()->hasRole('auditor'));
        $this->assertTrue($user->fresh()->hasPermissionTo('audit'));
    }

    #[Test]
    public function disabling_refuses_when_tenants_share_a_name()
    {
        config(['keystone.features.multi_tenant' => true]);
        KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_A]);
        KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_B]);
        $before = DB::table('roles')->pluck('tenant_id', 'id')->all();

        $this->artisan('keystone:disable-multi-tenancy', ['--force' => true])
            ->expectsOutputToContain("Role 'editor'")
            ->assertFailed();

        $this->assertSame($before, DB::table('roles')->pluck('tenant_id', 'id')->all());
    }

    #[Test]
    public function disabling_keeps_one_row_of_an_assignment_held_in_two_tenants()
    {
        config(['keystone.features.multi_tenant' => true]);
        $viewer = KeystoneRole::create(['name' => 'viewer']);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        $user->assignRole($viewer);
        DB::table('users')->where('id', $user->id)->update(['tenant_id' => self::TENANT_B]);
        $user->fresh()->assignRole($viewer);
        $this->assertSame(2, DB::table('model_has_roles')->where('role_id', $viewer->id)->count());

        $this->artisan('keystone:disable-multi-tenancy', ['--force' => true])->assertSuccessful();

        $rows = DB::table('model_has_roles')->where('role_id', $viewer->id)->get();
        $this->assertCount(1, $rows);
        $this->assertNull($rows->first()->tenant_id);
    }

    #[Test]
    public function a_failure_part_way_changes_nothing()
    {
        config(['keystone.features.multi_tenant' => true]);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        KeystoneRole::create(['name' => 'auditor', 'tenant_id' => self::TENANT_A]);
        $user->assignRole('auditor');
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'update "model_has_roles"')) {
                throw new \RuntimeException('connection lost');
            }
        });

        $this->artisan('keystone:disable-multi-tenancy', ['--force' => true])->assertFailed();

        $this->assertSame(self::TENANT_A, DB::table('roles')->value('tenant_id'));
        $this->assertSame(self::TENANT_A, DB::table('model_has_roles')->value('tenant_id'));
    }

    #[Test]
    public function disabling_twice_changes_nothing_the_second_time()
    {
        config(['keystone.features.multi_tenant' => true]);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        KeystoneRole::create(['name' => 'auditor', 'tenant_id' => self::TENANT_A]);
        $user->assignRole('auditor');
        $this->artisan('keystone:disable-multi-tenancy', ['--force' => true])->assertSuccessful();
        $after = [DB::table('roles')->get(), DB::table('model_has_roles')->get()];

        $this->artisan('keystone:disable-multi-tenancy', ['--force' => true])
            ->expectsOutputToContain('roles: 0 row(s) changed')
            ->assertSuccessful();

        $this->assertEquals($after, [DB::table('roles')->get(), DB::table('model_has_roles')->get()]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function singleTenantInstallWithData(): User
    {
        config(['keystone.features.multi_tenant' => false]);
        LegacySchema::singleTenant();

        DB::table('permissions')->insert(['name' => 'edit-posts', 'guard_name' => 'web']);
        $roleId = DB::table('roles')->insertGetId(['name' => 'editor', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->value('id')]);
        $user = User::factory()->create();
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => $user->getMorphClass(), 'model_id' => $user->id]);

        return $user;
    }

    private function assertUpgraded(User $user): void
    {
        foreach (self::TENANT_TABLES as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "{$table}.tenant_id should exist");
        }
        foreach (['roles', 'permissions', 'model_has_roles'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNotNull('tenant_id')->count(), "{$table} rows should stay global");
        }

        // Existing assignments keep working
        $this->assertTrue($user->fresh()->hasRole('editor'));
        $this->assertTrue($user->fresh()->hasPermissionTo('edit-posts'));

        config(['keystone.features.multi_tenant' => true]);

        // A tenant can reuse a global role's name
        KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_A]);
        $this->assertSame(2, KeystoneRole::withoutTenant()->where('name', 'editor')->count());

        // The same role can be held in two tenant contexts (tenant-aware pivot key)
        $manager = KeystoneRole::create(['name' => 'manager']);
        DB::table('users')->where('id', $user->id)->update(['tenant_id' => self::TENANT_B]);
        $user->fresh()->assignRole($manager);
        DB::table('users')->where('id', $user->id)->update(['tenant_id' => self::TENANT_A]);
        $user->fresh()->assignRole($manager);
        $this->assertSame(2, DB::table('model_has_roles')->where('role_id', $manager->id)->count());
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    private function schemaSnapshot(): array
    {
        return collect(self::TENANT_TABLES)
            ->mapWithKeys(fn ($table) => [$table => [Schema::getColumns($table), Schema::getIndexes($table)]])
            ->all();
    }

    private function migration(string $name): object
    {
        return require base_path("database/migrations/2024_01_01_{$name}.php");
    }
}
