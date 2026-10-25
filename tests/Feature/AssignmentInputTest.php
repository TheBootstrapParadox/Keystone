<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Assignment and check methods accept integer IDs everywhere, and mutating
 * methods keep already-loaded relations in sync.
 */
class AssignmentInputTest extends TestCase
{
    private KeystoneRole $editor;

    private KeystonePermission $publish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = KeystoneRole::create(['name' => 'editor']);
        $this->publish = KeystonePermission::create(['name' => 'publish-posts']);
    }

    #[Test]
    public function users_accept_integer_ids()
    {
        $user = User::factory()->create();

        $user->assignRole($this->editor->id);
        $user->givePermissionTo($this->publish->id);

        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasRole($this->editor->id));
        $this->assertTrue($user->hasDirectPermission('publish-posts'));
        $this->assertTrue($user->hasPermissionTo($this->publish->id));

        $user->removeRole($this->editor->id);
        $user->revokePermissionTo($this->publish->id);

        $this->assertFalse($user->hasRole('editor'));
        $this->assertFalse($user->hasDirectPermission('publish-posts'));
    }

    #[Test]
    public function an_unknown_id_throws_and_changes_nothing()
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        try {
            $user->assignRole(999999);
            $this->fail('Expected ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            $this->assertSame(['editor'], $user->fresh()->roles->pluck('name')->all());
        }
    }

    #[Test]
    public function roles_accept_permission_ids()
    {
        $this->editor->givePermissionTo($this->publish->id);

        $this->assertTrue($this->editor->fresh()->hasPermissionTo('publish-posts'));

        $this->editor->revokePermissionTo($this->publish->id);

        $this->assertFalse($this->editor->fresh()->hasPermissionTo('publish-posts'));
    }

    #[Test]
    public function a_roles_loaded_permissions_reflect_sync()
    {
        $delete = KeystonePermission::create(['name' => 'delete-posts']);
        $this->editor->givePermissionTo('publish-posts');
        $this->editor->load('permissions');

        $this->editor->syncPermissions($delete);

        $this->assertTrue($this->editor->hasPermissionTo('delete-posts'));
        $this->assertFalse($this->editor->hasPermissionTo('publish-posts'));
    }

    #[Test]
    public function a_permissions_loaded_roles_reflect_changes()
    {
        $this->publish->load('roles');

        $this->publish->assignRole($this->editor);
        $this->assertTrue($this->publish->hasRole('editor'));

        $this->publish->removeRole($this->editor);
        $this->assertFalse($this->publish->hasRole('editor'));

        $this->publish->syncRoles($this->editor);
        $this->assertTrue($this->publish->hasRole('editor'));
    }

    #[Test]
    public function permission_checks_do_not_query_once_per_role()
    {
        $queriesFor = function (int $roleCount): int {
            $user = User::factory()->create();
            foreach (range(1, $roleCount) as $i) {
                KeystonePermission::create(['name' => "perm-{$roleCount}-{$i}"]);
                $user->assignRole(KeystoneRole::create(['name' => "role-{$roleCount}-{$i}"])->givePermissionTo("perm-{$roleCount}-{$i}"));
            }
            $user = $user->fresh();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $user->hasPermissionTo('missing');
            $user->getAllPermissions();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->assertSame($queriesFor(1), $queriesFor(5));
    }
}
