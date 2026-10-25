<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\PermissionRegistrar;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Keystone's Gate::before callback grants held permissions and otherwise
 * defers to policies and defined gates.
 */
class GateIntegrationTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    #[Test]
    public function a_held_permission_is_allowed()
    {
        KeystonePermission::create(['name' => 'edit-posts']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-posts');

        $this->assertTrue($user->can('edit-posts'));
    }

    #[Test]
    public function a_missing_permission_without_a_gate_is_denied()
    {
        KeystonePermission::create(['name' => 'edit-posts']);
        $user = User::factory()->create();

        $this->assertFalse($user->can('edit-posts'));
    }

    #[Test]
    public function a_defined_gate_decides_when_the_user_lacks_a_same_named_permission()
    {
        KeystonePermission::create(['name' => 'update-post']);
        Gate::define('update-post', fn () => true);
        $user = User::factory()->create();

        $this->assertTrue($user->can('update-post'));
    }

    #[Test]
    public function another_tenants_permission_does_not_affect_a_defined_gate()
    {
        $this->requireTenantSchema();
        config(['keystone.features.multi_tenant' => true]);

        KeystonePermission::withoutTenant()->create(['name' => 'update', 'tenant_id' => self::TENANT_B]);
        Gate::define('update', fn () => true);
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);

        $this->assertTrue($user->can('update'));
    }

    #[Test]
    public function an_ability_that_is_not_a_permission_does_not_query_permissions()
    {
        KeystonePermission::create(['name' => 'edit-posts']);
        Gate::define('publish-report', fn () => true);
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->getAllPermissionNames(); // warm the cache

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->assertTrue($user->can('publish-report'));

        $permissionQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'from "permissions"'));
        $this->assertSame([], array_values($permissionQueries));
    }

    #[Test]
    public function the_callback_is_registered_outside_http_and_tests()
    {
        // Simulate a queue worker / scheduler: console, not the testing env.
        $this->app['env'] = 'production';
        $this->app->forgetInstance(GateContract::class);

        KeystonePermission::create(['name' => 'edit-posts']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-posts');

        $this->assertTrue($this->app->make(GateContract::class)->forUser($user)->allows('edit-posts'));
    }

    #[Test]
    public function registering_the_callback_makes_no_queries()
    {
        $this->app->forgetInstance(GateContract::class);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->app->make(GateContract::class);

        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function refresh_does_not_stack_callbacks()
    {
        $gate = $this->app->make(GateContract::class);
        $before = $this->beforeCallbackCount($gate);

        app(PermissionRegistrar::class)->refresh($gate);
        app(PermissionRegistrar::class)->refresh($gate);

        $this->assertSame($before, $this->beforeCallbackCount($gate));
    }

    private function beforeCallbackCount(GateContract $gate): int
    {
        return count((fn () => $this->beforeCallbacks)->call($gate));
    }

    private function requireTenantSchema(): void
    {
        if (! Schema::hasColumn('permissions', 'tenant_id')) {
            $this->markTestSkipped('Requires the multi-tenant schema.');
        }
    }
}
