<?php

namespace Tests\Feature;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * --tenant picks the tenant commands resolve role/permission names in;
 * without it, multi-tenant commands only touch global rows.
 */
class TenantCommandOptionTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    #[Test]
    public function a_role_lookup_without_tenant_only_matches_global_roles()
    {
        $this->enableMultiTenancy();
        KeystonePermission::create(['name' => 'edit-posts']);
        $roleA = KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_A]);
        $roleB = KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_B]);

        $this->artisan('keystone:assign-permission', ['permission' => ['edit-posts'], '--to-role' => 'editor'])
            ->assertFailed();

        $this->assertSame([], $roleA->fresh()->getPermissionNames()->all());
        $this->assertSame([], $roleB->fresh()->getPermissionNames()->all());
    }

    #[Test]
    public function a_role_lookup_with_tenant_targets_that_tenant()
    {
        $this->enableMultiTenancy();
        KeystonePermission::create(['name' => 'edit-posts']);
        $roleA = KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_A]);
        $roleB = KeystoneRole::create(['name' => 'editor', 'tenant_id' => self::TENANT_B]);

        $this->artisan('keystone:assign-permission', ['permission' => ['edit-posts'], '--to-role' => 'editor', '--tenant' => self::TENANT_B])
            ->assertSuccessful();

        $this->assertSame([], $roleA->fresh()->getPermissionNames()->all());
        $this->assertSame(['edit-posts'], $roleB->fresh()->getPermissionNames()->all());

        $this->artisan('keystone:unassign-permission', ['--from-role' => 'editor', '--all' => true, '--tenant' => self::TENANT_B])
            ->assertSuccessful();

        $this->assertSame([], $roleB->fresh()->getPermissionNames()->all());
    }

    #[Test]
    public function make_role_and_permission_create_in_the_given_tenant()
    {
        $this->enableMultiTenancy();

        $this->artisan('keystone:make-permission', ['name' => 'approve', '--tenant' => self::TENANT_A])->assertSuccessful();
        $this->artisan('keystone:make-role', ['name' => 'manager', '--tenant' => self::TENANT_A, '--permissions' => 'approve'])->assertSuccessful();
        $this->artisan('keystone:make-role', ['name' => 'auditor'])->assertSuccessful();

        $manager = KeystoneRole::withoutTenant()->where('name', 'manager')->first();
        $this->assertSame(self::TENANT_A, $manager->tenant_id);
        $this->assertSame(self::TENANT_A, KeystonePermission::withoutTenant()->where('name', 'approve')->value('tenant_id'));
        $this->assertSame(['approve'], $manager->getPermissionNames()->all());
        $this->assertNull(KeystoneRole::withoutTenant()->where('name', 'auditor')->value('tenant_id'));
    }

    #[Test]
    public function the_reserved_super_admin_name_fails_in_a_tenant()
    {
        $this->enableMultiTenancy();

        $this->artisan('keystone:make-role', ['name' => 'super-admin', '--tenant' => self::TENANT_A])->assertFailed();

        $this->assertFalse(KeystoneRole::withoutTenant()->where('name', 'super-admin')->exists());
    }

    #[Test]
    public function an_invalid_tenant_is_rejected()
    {
        $this->enableMultiTenancy();

        $this->artisan('keystone:make-role', ['name' => 'manager', '--tenant' => 'not-a-uuid'])->assertFailed();

        $this->assertFalse(KeystoneRole::withoutTenant()->where('name', 'manager')->exists());
    }

    #[Test]
    public function tenant_is_rejected_in_single_tenant_mode()
    {
        config(['keystone.features.multi_tenant' => false]);

        $this->artisan('keystone:make-role', ['name' => 'manager', '--tenant' => self::TENANT_A])
            ->expectsOutputToContain('Multi-tenancy is not enabled')
            ->assertFailed();

        $this->assertFalse(KeystoneRole::withoutTenant()->where('name', 'manager')->exists());
    }

    private function enableMultiTenancy(): void
    {
        if (! Schema::hasColumn('roles', 'tenant_id')) {
            $this->markTestSkipped('Requires the multi-tenant schema.');
        }

        config(['keystone.features.multi_tenant' => true]);
    }
}
