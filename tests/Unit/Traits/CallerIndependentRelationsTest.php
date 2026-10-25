<?php

namespace Tests\Unit\Traits;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What a user (or role) holds must not depend on who is logged in, and
 * assignments stored with a NULL pivot tenant stay visible.
 */
class CallerIndependentRelationsTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => true]);
    }

    #[Test]
    public function a_global_pivot_assignment_is_visible_after_the_user_joins_a_tenant()
    {
        KeystoneRole::create(['name' => 'viewer']);
        $user = User::factory()->create();
        $user->assignRole('viewer');

        DB::table('users')->where('id', $user->id)->update(['tenant_id' => self::TENANT_A]);

        $this->assertTrue($user->fresh()->hasRole('viewer'));
    }

    #[Test]
    public function an_other_tenant_pivot_assignment_stays_hidden()
    {
        KeystoneRole::create(['name' => 'editor']);
        $user = User::factory()->create(['tenant_id' => self::TENANT_B]);
        $user->assignRole('editor');

        DB::table('users')->where('id', $user->id)->update(['tenant_id' => self::TENANT_A]);

        $this->assertFalse($user->fresh()->hasRole('editor'));
    }

    #[Test]
    public function a_users_permissions_do_not_depend_on_the_authenticated_caller()
    {
        $permission = KeystonePermission::create(['name' => 'approve-invoices', 'tenant_id' => self::TENANT_B]);
        $role = KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_B])->givePermissionTo($permission);
        $userB = User::factory()->create(['tenant_id' => self::TENANT_B]);
        $userB->assignRole($role);

        $this->assertTrue($userB->fresh()->hasPermissionTo('approve-invoices'));

        Auth::login(User::factory()->create(['tenant_id' => self::TENANT_A]));

        $fresh = $userB->fresh();
        $this->assertTrue($fresh->hasRole('manager'));
        $this->assertTrue($fresh->hasPermissionTo('approve-invoices'));
        $this->assertSame(['approve-invoices'], $fresh->getAllPermissions()->pluck('name')->all());
    }

    #[Test]
    public function a_roles_permissions_do_not_depend_on_the_authenticated_caller()
    {
        $permission = KeystonePermission::create(['name' => 'approve-invoices', 'tenant_id' => self::TENANT_B]);
        $role = KeystoneRole::create(['name' => 'manager', 'tenant_id' => self::TENANT_B])->givePermissionTo($permission);

        Auth::login(User::factory()->create(['tenant_id' => self::TENANT_A]));

        $this->assertSame(['approve-invoices'], KeystoneRole::withoutTenant()->find($role->id)->getPermissionNames()->all());
    }
}
