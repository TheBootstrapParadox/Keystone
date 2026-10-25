<?php

namespace Tests\Unit\Models;

use BSPDX\Keystone\Exceptions\PermissionAlreadyExists;
use BSPDX\Keystone\Exceptions\RoleAlreadyExists;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unique keys treat NULL tenants as distinct, so multi-tenant mode guards
 * against duplicate global rows at the model level.
 */
class DuplicateNameGuardTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => true]);
    }

    #[Test]
    public function a_second_global_role_with_the_same_name_is_rejected()
    {
        KeystoneRole::create(['name' => 'manager']);

        try {
            KeystoneRole::create(['name' => 'manager']);
            $this->fail('Expected RoleAlreadyExists.');
        } catch (RoleAlreadyExists) {
            $this->assertSame(1, KeystoneRole::withoutTenant()->where('name', 'manager')->count());
        }
    }

    #[Test]
    public function a_second_global_permission_with_the_same_name_is_rejected()
    {
        KeystonePermission::create(['name' => 'edit-posts']);

        $this->expectException(PermissionAlreadyExists::class);

        KeystonePermission::create(['name' => 'edit-posts']);
    }

    #[Test]
    public function renaming_onto_an_existing_name_is_rejected()
    {
        KeystoneRole::create(['name' => 'manager']);
        $other = KeystoneRole::create(['name' => 'auditor']);

        $this->expectException(RoleAlreadyExists::class);

        $other->update(['name' => 'manager']);
    }

    #[Test]
    public function the_same_name_is_allowed_across_tenants_and_guards()
    {
        KeystoneRole::create(['name' => 'manager']);
        KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_A]);
        KeystoneRole::create(['name' => 'manager', 'guard_name' => 'api']);

        $this->assertSame(3, KeystoneRole::withoutTenant()->where('name', 'manager')->count());
    }

    #[Test]
    public function saving_a_row_without_changes_is_allowed()
    {
        $role = KeystoneRole::create(['name' => 'manager']);

        $role->update(['title' => 'Manager']);

        $this->assertSame('Manager', $role->fresh()->title);
    }
}
