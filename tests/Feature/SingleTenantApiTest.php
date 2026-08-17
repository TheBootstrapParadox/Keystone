<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for a single-tenant install (KEYSTONE_MULTI_TENANT=false).
 *
 * Every method here previously threw "SQLSTATE[42S22]: Unknown column 'tenant_id'"
 * because roles()/permissions()/users() called withPivot('tenant_id')
 * unconditionally, even though the pivot migration only adds that column when
 * multi-tenancy is enabled. Run via: composer test-single-tenant
 */
class SingleTenantApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => false]);
    }

    #[Test]
    public function full_role_and_permission_api_works_without_tenant_id_column()
    {
        $user = User::factory()->create();
        $role = KeystoneRole::create(['name' => 'editor']);
        $permission = KeystonePermission::create(['name' => 'edit-posts']);

        $role->givePermissionTo($permission);
        $this->assertTrue($role->permissions()->get()->contains('name', 'edit-posts'));

        $user->assignRole($role);
        $this->assertTrue($user->roles()->get()->contains('name', 'editor'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($role->users()->get()->contains('id', $user->id));

        $user->givePermissionTo('edit-posts');
        $this->assertTrue($user->permissions()->get()->contains('name', 'edit-posts'));
        $this->assertTrue($user->hasDirectPermission('edit-posts'));
        $this->assertTrue($user->getAllPermissions()->contains('name', 'edit-posts'));

        $user->revokePermissionTo('edit-posts');
        $this->assertFalse($user->fresh()->hasDirectPermission('edit-posts'));

        $user->syncPermissions('edit-posts');
        $this->assertTrue($user->hasDirectPermission('edit-posts'));

        $user->removeRole('editor');
        $this->assertFalse($user->fresh()->hasRole('editor'));

        $user->syncRoles('editor');
        $this->assertTrue($user->hasRole('editor'));
    }
}
