<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Exceptions\CannotDeleteSuperAdminRole;
use BSPDX\Keystone\Exceptions\PermissionAlreadyExists;
use BSPDX\Keystone\Exceptions\ReservedRoleName;
use BSPDX\Keystone\Exceptions\RoleAlreadyExists;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\PresenceVerifierInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Keystone's exceptions reach clients as JSON client errors and are not
 * reported as faults.
 */
class KeystoneExceptionRenderingTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web'])->group(base_path('routes/api.php'));
    }

    #[Test]
    public function a_duplicate_role_that_passes_validation_is_a_conflict()
    {
        KeystoneRole::create(['name' => 'moderator']);
        // Another request inserted the same name after this one was validated
        $this->app->instance('validation.presence', $this->presenceVerifierThatSeesNothing());

        $this->actingAs($this->callerWith('create-roles'))
            ->postJson('/api/roles', ['name' => 'moderator'])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame(1, KeystoneRole::withoutTenant()->where('name', 'moderator')->count());
    }

    #[Test]
    public function a_reserved_name_reaching_the_model_is_unprocessable()
    {
        config(['keystone.features.multi_tenant' => true]);
        Route::post('/keystone-test/reserved', fn () => KeystoneRole::create(['name' => 'super-admin', 'tenant_id' => self::TENANT_A]));

        $this->postJson('/keystone-test/reserved')
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertFalse(KeystoneRole::withoutTenant()->where('name', 'super-admin')->exists());
    }

    #[Test]
    public function deleting_the_super_admin_role_stays_forbidden()
    {
        $role = KeystoneRole::create(['name' => 'super-admin']);
        $caller = User::factory()->create();
        $caller->assignRole($role);

        $this->actingAs($caller)->deleteJson("/api/roles/{$role->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Cannot delete the super admin role.']);
    }

    #[Test]
    public function other_exceptions_are_left_to_the_application()
    {
        Route::get('/keystone-test/broken', fn () => throw new RuntimeException('SQLSTATE[HY000]: something broke'));

        $this->getJson('/keystone-test/broken')->assertStatus(500);
    }

    #[Test]
    public function keystone_exceptions_are_not_reported()
    {
        Exceptions::fake();

        foreach ([
            RoleAlreadyExists::create('editor', 'web', null),
            PermissionAlreadyExists::create('edit-posts', 'web', null),
            ReservedRoleName::forTenant('super-admin'),
            new CannotDeleteSuperAdminRole,
        ] as $exception) {
            report($exception);
        }

        Exceptions::assertNothingReported();
    }

    private function callerWith(string $permission): User
    {
        KeystonePermission::create(['name' => $permission]);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function presenceVerifierThatSeesNothing(): PresenceVerifierInterface
    {
        return new class implements PresenceVerifierInterface
        {
            public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
            {
                return 0;
            }

            public function getMultiCount($collection, $column, array $values, array $extra = []): int
            {
                return 0;
            }
        };
    }
}
