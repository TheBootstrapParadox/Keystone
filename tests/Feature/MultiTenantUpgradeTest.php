<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An install that first migrated in single-tenant mode can enable
 * multi-tenancy and migrate again.
 */
#[Group('ddl')]
class MultiTenantUpgradeTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    #[Test]
    public function the_command_upgrades_a_single_tenant_install()
    {
        $user = $this->singleTenantInstallWithData();

        config(['keystone.features.multi_tenant' => true]);
        $this->artisan('keystone:enable-multi-tenancy')->assertSuccessful();

        $this->assertUpgraded($user);

        $this->artisan('keystone:enable-multi-tenancy')
            ->expectsOutputToContain('already multi-tenant')
            ->assertSuccessful();
    }

    #[Test]
    public function the_migration_upgrades_a_single_tenant_install()
    {
        $user = $this->singleTenantInstallWithData();

        config(['keystone.features.multi_tenant' => true]);
        $this->migration('00003_add_tenant_id_to_pivot_tables')->up();
        $this->migration('00004_upgrade_roles_permissions_for_multi_tenant')->up();

        $this->assertUpgraded($user);
    }

    #[Test]
    public function the_command_fails_when_multi_tenancy_is_off()
    {
        $this->rebuildSingleTenantSchema();

        $this->artisan('keystone:enable-multi-tenancy')
            ->expectsOutputToContain('Multi-tenancy is not enabled')
            ->assertFailed();

        $this->assertFalse(Schema::hasColumn('roles', 'tenant_id'));
    }

    private function singleTenantInstallWithData(): User
    {
        $this->rebuildSingleTenantSchema();

        KeystonePermission::create(['name' => 'edit-posts']);
        KeystoneRole::create(['name' => 'editor'])->givePermissionTo('edit-posts');
        $user = User::factory()->create();
        $user->assignRole('editor');

        return $user;
    }

    private function assertUpgraded(User $user): void
    {
        foreach (['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "{$table}.tenant_id should exist");
        }
        $this->assertNull(KeystoneRole::where('name', 'editor')->value('tenant_id'));
        $this->assertNull(KeystonePermission::where('name', 'edit-posts')->value('tenant_id'));

        // Existing assignments keep working
        $user = $user->fresh();
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasPermissionTo('edit-posts'));

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

    #[Test]
    public function the_upgrade_changes_nothing_on_a_fresh_multi_tenant_install()
    {
        if (! Schema::hasColumn('roles', 'tenant_id')) {
            $this->markTestSkipped('Requires the multi-tenant schema.');
        }
        config(['keystone.features.multi_tenant' => true]);
        $indexes = fn () => collect(['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions'])
            ->mapWithKeys(fn ($table) => [$table => Schema::getIndexes($table)])
            ->all();
        $before = $indexes();

        $this->migration('00004_upgrade_roles_permissions_for_multi_tenant')->up();

        $this->assertEquals($before, $indexes());
    }

    #[Test]
    public function the_upgrade_does_nothing_when_multi_tenancy_is_off()
    {
        $this->rebuildSingleTenantSchema();

        $this->migration('00004_upgrade_roles_permissions_for_multi_tenant')->up();

        $this->assertFalse(Schema::hasColumn('roles', 'tenant_id'));
    }

    /**
     * Recreate Keystone's tables (and the users table) as a single-tenant
     * install would have them.
     */
    private function rebuildSingleTenantSchema(): void
    {
        $this->migration('00001_add_keystone_fields_to_users_table')->down();
        $this->migration('00002_create_permission_tables')->down();

        config(['keystone.features.multi_tenant' => false]);
        $this->migration('00001_add_keystone_fields_to_users_table')->up();
        $this->migration('00002_create_permission_tables')->up();

        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('roles', 'tenant_id'));
    }

    private function migration(string $name): object
    {
        return require base_path("database/migrations/2024_01_01_{$name}.php");
    }
}
