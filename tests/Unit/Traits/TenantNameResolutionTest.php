<?php

namespace Tests\Unit\Traits;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Names given to assignment methods resolve in the receiving model's tenant,
 * never in the (possibly tenant-less) caller's scope.
 */
class TenantNameResolutionTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => true]);
    }

    #[Test]
    public function tenantless_caller_assigns_the_targets_tenant_role()
    {
        $this->role('manager', self::TENANT_A);
        $roleB = $this->role('manager', self::TENANT_B);
        $user = $this->user(self::TENANT_B);

        $user->assignRole('manager');

        $this->assertSame([$roleB->id], $this->heldRoleIds($user));
    }

    #[Test]
    public function tenant_role_is_preferred_over_global_role()
    {
        $this->role('manager', null);
        $roleB = $this->role('manager', self::TENANT_B);
        $user = $this->user(self::TENANT_B);

        $user->assignRole('manager');

        $this->assertSame([$roleB->id], $this->heldRoleIds($user));
    }

    #[Test]
    public function global_role_is_used_when_the_tenant_has_none()
    {
        $global = $this->role('auditor', null);
        $user = $this->user(self::TENANT_B);

        $user->assignRole('auditor');

        $this->assertSame([$global->id], $this->heldRoleIds($user));
    }

    #[Test]
    public function name_that_exists_only_in_another_tenant_is_not_found()
    {
        $this->role('billing', self::TENANT_A);
        $user = $this->user(self::TENANT_B);

        try {
            $user->assignRole('billing');
            $this->fail('Expected ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            $this->assertSame([], $this->heldRoleIds($user));
        }
    }

    #[Test]
    public function tenantless_user_resolves_only_global_rows()
    {
        $this->role('manager', self::TENANT_A);
        $user = $this->user(null);

        $this->expectException(ModelNotFoundException::class);

        $user->assignRole('manager');
    }

    #[Test]
    public function remove_role_by_name_targets_the_targets_tenant_row()
    {
        $this->role('manager', self::TENANT_A);
        $roleB = $this->role('manager', self::TENANT_B);
        $user = $this->user(self::TENANT_B);
        $user->assignRole($roleB);

        $user->removeRole('manager');

        $this->assertSame([], $this->heldRoleIds($user));
    }

    #[Test]
    public function direct_permission_by_name_resolves_in_the_users_tenant()
    {
        $this->permission('edit-invoices', self::TENANT_A);
        $permB = $this->permission('edit-invoices', self::TENANT_B);
        $user = $this->user(self::TENANT_B);

        $user->givePermissionTo('edit-invoices');

        $this->assertSame([$permB->id], $user->permissions()->pluck('permissions.id')->all());
    }

    #[Test]
    public function explicit_model_instance_is_used_as_given()
    {
        $roleA = $this->role('manager', self::TENANT_A);
        $this->role('manager', self::TENANT_B);
        $user = $this->user(self::TENANT_B);

        $user->assignRole($roleA);

        $this->assertSame([$roleA->id], $this->heldRoleIds($user));
    }

    #[Test]
    public function role_permissions_by_name_resolve_in_the_roles_tenant()
    {
        $this->permission('edit-invoices', self::TENANT_A);
        $permB = $this->permission('edit-invoices', self::TENANT_B);
        $roleB = $this->role('manager', self::TENANT_B);

        $roleB->givePermissionTo('edit-invoices');

        $this->assertSame([$permB->id], $roleB->permissions()->pluck('permissions.id')->all());
    }

    #[Test]
    public function permission_roles_by_name_resolve_in_the_permissions_tenant()
    {
        $this->role('manager', self::TENANT_A);
        $roleB = $this->role('manager', self::TENANT_B);
        $permB = $this->permission('edit-invoices', self::TENANT_B);

        $permB->assignRole('manager');

        $this->assertSame([$roleB->id], $permB->roles()->pluck('roles.id')->all());
    }

    private function role(string $name, ?string $tenantId): KeystoneRole
    {
        return KeystoneRole::withoutTenant()->create(['name' => $name, 'tenant_id' => $tenantId]);
    }

    private function permission(string $name, ?string $tenantId): KeystonePermission
    {
        return KeystonePermission::withoutTenant()->create(['name' => $name, 'tenant_id' => $tenantId]);
    }

    private function user(?string $tenantId): User
    {
        return User::factory()->create(['tenant_id' => $tenantId]);
    }

    /**
     * Role IDs actually stored on the user's pivot, unaffected by any scope.
     *
     * @return list<int>
     */
    private function heldRoleIds(User $user): array
    {
        return DB::table('model_has_roles')
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->id)
            ->orderBy('role_id')
            ->pluck('role_id')
            ->all();
    }
}
