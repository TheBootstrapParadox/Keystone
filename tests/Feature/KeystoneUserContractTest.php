<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Fixtures\PlainUser;
use Tests\Fixtures\UuidUser;
use Tests\TestCase;
use TypeError;

/**
 * The KeystoneUser contract, the typed signatures behind it, and how users
 * outside Keystone are treated.
 */
class KeystoneUserContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        KeystoneRole::create(['name' => 'editor']);
        KeystoneRole::create(['name' => 'admin']);
        KeystonePermission::create(['name' => 'edit-posts']);
        KeystonePermission::create(['name' => 'view-posts']);
    }

    // ------------------------------------------------------------------
    // User model contract
    // ------------------------------------------------------------------

    #[Test]
    public function the_trait_satisfies_the_interface()
    {
        $this->assertInstanceOf(KeystoneUser::class, new User);
    }

    #[Test]
    public function a_configured_user_model_without_the_interface_is_refused_by_commands()
    {
        config(['keystone.user.model' => PlainUser::class]);
        $user = User::factory()->create();

        try {
            $this->artisan('keystone:assign-role', ['user' => (string) $user->id, 'role' => ['admin']])->run();
            $this->fail('Expected a LogicException.');
        } catch (LogicException $e) {
            $this->assertStringContainsString(PlainUser::class, $e->getMessage());
            $this->assertStringContainsString('KeystoneUser', $e->getMessage());
        }

        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    #[Test]
    #[Group('ddl')]
    public function a_second_model_that_opts_in_works_through_the_services()
    {
        Schema::create('uuid_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->timestamps();
        });
        $admin = UuidUser::create();

        app(RoleServiceInterface::class)->assignToUser($admin, 'editor');

        $this->assertTrue(app(AuthorizationServiceInterface::class)->userHasRole($admin->fresh(), 'editor'));
    }

    // ------------------------------------------------------------------
    // Services take a KeystoneUser
    // ------------------------------------------------------------------

    #[Test]
    public function services_reject_a_model_without_the_interface()
    {
        $this->expectException(TypeError::class);

        app(AuthorizationServiceInterface::class)->userHasPermission(new PlainUser, 'edit-posts');
    }

    // ------------------------------------------------------------------
    // Users outside Keystone
    // ------------------------------------------------------------------

    #[Test]
    public function middleware_refuses_an_authenticated_user_outside_keystone()
    {
        Route::middleware(['web', 'permission:edit-posts'])->get('/outside', fn () => 'ok');
        $user = PlainUser::create(['name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'x']);
        $user->givePermissionTo('edit-posts');

        $this->actingAs($user)->getJson('/outside')->assertForbidden();
    }

    #[Test]
    public function an_unauthenticated_request_is_still_401()
    {
        Route::middleware(['web', 'role:admin'])->get('/admin-only', fn () => 'ok');

        $this->getJson('/admin-only')->assertUnauthorized();
    }

    #[Test]
    public function the_gate_leaves_users_outside_keystone_to_policies()
    {
        Gate::define('update', fn () => true);
        KeystonePermission::create(['name' => 'update']);
        $user = PlainUser::create(['name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'x']);

        $this->assertTrue(Gate::forUser($user)->allows('update'));
    }

    // ------------------------------------------------------------------
    // Typed signatures
    // ------------------------------------------------------------------

    #[Test]
    public function existing_call_styles_still_work()
    {
        $user = User::factory()->create();
        $editor = KeystoneRole::firstWhere('name', 'editor');

        $user->assignRole('editor');
        $user->assignRole(['editor', 'admin']);
        $user->assignRole($editor);
        $user->assignRole(collect([$editor]));
        $user->assignRole($editor->id);

        $this->assertEqualsCanonicalizing(['editor', 'admin'], $user->fresh()->roles->pluck('name')->all());
        $this->assertTrue($user->hasAllRoles(collect(['editor', 'admin'])));
    }

    #[Test]
    public function an_unsupported_role_argument_is_a_type_error()
    {
        $this->expectException(TypeError::class);

        User::factory()->create()->hasRole(new stdClass);
    }

    #[Test]
    public function a_role_accepts_a_collection_of_permissions()
    {
        $role = KeystoneRole::firstWhere('name', 'editor');

        $role->givePermissionTo(collect(['edit-posts', 'view-posts']));

        $this->assertEqualsCanonicalizing(['edit-posts', 'view-posts'], $role->getPermissionNames()->all());
    }

    #[Test]
    public function an_unsupported_permission_argument_is_a_type_error_and_attaches_nothing()
    {
        $role = KeystoneRole::firstWhere('name', 'editor');

        try {
            $role->givePermissionTo(new stdClass);
            $this->fail('Expected a TypeError.');
        } catch (TypeError) {
        }

        $this->assertSame([], $role->fresh()->getPermissionNames()->all());
    }
}
