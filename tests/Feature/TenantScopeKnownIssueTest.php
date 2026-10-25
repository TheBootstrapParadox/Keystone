<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the documented Known Issue: the tenant filter on role and permission
 * list queries follows the logged-in user, and the documented ways to name a
 * tenant explicitly.
 */
class TenantScopeKnownIssueTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    private const TENANT_B = '019c17d7-2b98-82cf-b7b5-82be35f4c8fa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.multi_tenant' => true]);
        KeystoneRole::create(['name' => 'shared']);
        KeystoneRole::create(['name' => 'a-only', 'tenant_id' => self::TENANT_A]);
        KeystoneRole::create(['name' => 'b-only', 'tenant_id' => self::TENANT_B]);
    }

    #[Test]
    public function list_queries_follow_the_logged_in_users_tenant()
    {
        $this->actingAs(User::factory()->create(['tenant_id' => self::TENANT_A]));

        $this->assertEqualsCanonicalizing(['shared', 'a-only'], KeystoneRole::pluck('name')->all());
    }

    #[Test]
    public function list_queries_are_unscoped_without_a_logged_in_user()
    {
        $this->assertEqualsCanonicalizing(['shared', 'a-only', 'b-only'], KeystoneRole::pluck('name')->all());
    }

    #[Test]
    public function naming_the_tenant_explicitly_returns_only_its_rows()
    {
        $this->assertSame(['a-only'], KeystoneRole::withoutTenant()->forTenant(self::TENANT_A)->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['shared', 'a-only'],
            KeystoneRole::withoutTenant()->where(fn ($query) => $query->forTenant(self::TENANT_A)->orWhereNull('tenant_id'))->pluck('name')->all()
        );
        $this->assertSame(['a-only'], app(RoleServiceInterface::class)->getAllForTenant(self::TENANT_A)->pluck('name')->all());
    }

    #[Test]
    public function what_a_user_holds_does_not_depend_on_who_is_logged_in()
    {
        $member = User::factory()->create(['tenant_id' => self::TENANT_B]);
        $member->assignRole('b-only');

        $this->actingAs(User::factory()->create(['tenant_id' => self::TENANT_A]));

        $this->assertTrue($member->fresh()->hasRole('b-only'));
    }
}
