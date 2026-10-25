<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\CustomKeyUser;
use Tests\TestCase;

/**
 * Replacing a user's assignments is all-or-nothing, and every assignment
 * method follows the model's primary key.
 */
class UserSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['edit-posts', 'publish-posts'] as $name) {
            KeystonePermission::create(['name' => $name]);
        }
        foreach (['editor', 'admin'] as $name) {
            KeystoneRole::create(['name' => $name]);
        }
    }

    #[Test]
    public function an_unknown_permission_in_a_sync_leaves_direct_permissions_unchanged()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('edit-posts', 'publish-posts');

        try {
            $user->syncPermissions(['edit-posts', 'does-not-exist']);
            $this->fail('Expected a ModelNotFoundException.');
        } catch (ModelNotFoundException) {
        }

        $this->assertEqualsCanonicalizing(['edit-posts', 'publish-posts'], $user->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function an_unknown_role_in_a_sync_leaves_roles_unchanged()
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        try {
            $user->syncRoles(['admin', 'does-not-exist']);
            $this->fail('Expected a ModelNotFoundException.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(['editor'], $user->fresh()->roles->pluck('name')->all());
    }

    #[Test]
    public function a_failed_console_sync_keeps_the_users_permissions()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('edit-posts');

        $this->artisan('keystone:assign-permission', ['permission' => ['does-not-exist'], '--to-user' => $user->id, '--sync' => true])
            ->assertFailed();

        $this->assertSame(['edit-posts'], $user->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function a_failed_insert_rolls_the_removal_back()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('edit-posts');
        $user->assignRole('editor');
        // A model whose ID has no row: the insert fails on the foreign key
        $missingPermission = (new KeystonePermission)->forceFill(['id' => 999999, 'name' => 'ghost']);
        $missingRole = (new KeystoneRole)->forceFill(['id' => 999999, 'name' => 'ghost']);

        try {
            $user->syncPermissions([$missingPermission]);
            $this->fail('Expected a QueryException.');
        } catch (QueryException) {
        }
        try {
            $user->syncRoles([$missingRole]);
            $this->fail('Expected a QueryException.');
        } catch (QueryException) {
        }

        $user = $user->fresh();
        $this->assertSame(['edit-posts'], $user->permissions->pluck('name')->all());
        $this->assertSame(['editor'], $user->roles->pluck('name')->all());
    }

    #[Test]
    #[Group('ddl')]
    public function assignment_methods_follow_a_custom_primary_key()
    {
        Schema::create('custom_key_users', function (Blueprint $table) {
            $table->id('user_id');
            $table->uuid('tenant_id')->nullable();
            $table->timestamps();
        });
        $user = CustomKeyUser::create();

        $user->assignRole('editor')->removeRole('editor');
        $this->assertFalse($user->fresh()->hasRole('editor'));

        $user->assignRole('editor')->syncRoles(['admin']);
        $this->assertSame(['admin'], $user->fresh()->roles->pluck('name')->all());

        $user->givePermissionTo('edit-posts')->revokePermissionTo('edit-posts');
        $this->assertFalse($user->fresh()->hasDirectPermission('edit-posts'));

        $user->givePermissionTo('edit-posts')->syncPermissions(['publish-posts']);
        $this->assertSame(['publish-posts'], $user->fresh()->permissions->pluck('name')->all());
    }
}
