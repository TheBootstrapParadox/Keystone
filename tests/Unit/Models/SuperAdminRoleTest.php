<?php

namespace Tests\Unit\Models;

use App\Models\User;
use BSPDX\Keystone\Exceptions\ReservedRoleName;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * In multi-tenant mode only a global role can make a user a super-admin, and
 * the super-admin name cannot be taken by a tenant.
 */
class SuperAdminRoleTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => true]);
    }

    #[Test]
    public function a_tenant_scoped_super_admin_role_confers_nothing()
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'super-admin',
            'guard_name' => 'web',
            'tenant_id' => self::TENANT_A,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $role = KeystoneRole::withoutTenant()->findOrFail($roleId);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        $user->assignRole($role);

        $this->assertFalse($role->isSuperAdmin());
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasPermissionTo('anything'));
    }

    #[Test]
    public function a_global_super_admin_role_still_works_for_tenant_users()
    {
        $role = KeystoneRole::create(['name' => 'super-admin']);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);
        $user->assignRole($role);

        $this->assertNull($role->tenant_id);
        $this->assertTrue($role->isSuperAdmin());
        $this->assertTrue($user->isSuperAdmin());
    }

    #[Test]
    public function a_tenant_user_cannot_create_a_super_admin_role()
    {
        Auth::login(User::factory()->create(['tenant_id' => self::TENANT_A]));

        try {
            KeystoneRole::create(['name' => 'super-admin']);
            $this->fail('Expected ReservedRoleName.');
        } catch (ReservedRoleName) {
            $this->assertSame(0, KeystoneRole::withoutTenant()->where('name', 'super-admin')->count());
        }
    }

    #[Test]
    public function the_reserved_name_cannot_be_given_to_an_explicit_tenant_role()
    {
        $this->expectException(ReservedRoleName::class);

        KeystoneRole::create(['name' => 'super-admin', 'tenant_id' => self::TENANT_A]);
    }

    #[Test]
    public function a_tenant_role_cannot_be_renamed_to_the_reserved_name()
    {
        $role = KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_A]);

        try {
            $role->update(['name' => 'super-admin']);
            $this->fail('Expected ReservedRoleName.');
        } catch (ReservedRoleName) {
            $this->assertSame('manager', KeystoneRole::withoutTenant()->find($role->id)->name);
        }
    }

    #[Test]
    public function a_custom_super_admin_name_is_reserved_too()
    {
        config(['keystone.rbac.super_admin_role' => 'root']);

        $this->expectException(ReservedRoleName::class);

        KeystoneRole::create(['name' => 'root', 'tenant_id' => self::TENANT_A]);
    }
}
