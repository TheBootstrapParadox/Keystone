<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceFindByNameTest extends TestCase
{
    private const TENANT_A = '019c17d6-1a87-71be-a6a4-718da52579e9';

    /**
     * @return array<string, array{bool}>
     */
    public static function insertionOrders(): array
    {
        return ['global first' => [true], 'tenant first' => [false]];
    }

    #[Test]
    #[DataProvider('insertionOrders')]
    public function the_tenant_row_is_preferred_over_a_global_row(bool $globalFirst)
    {
        config(['keystone.features.multi_tenant' => true]);

        $create = fn (string $model, ?string $tenant) => $model::create(['name' => 'editor', 'tenant_id' => $tenant]);
        foreach ([KeystoneRole::class, KeystonePermission::class] as $model) {
            if ($globalFirst) {
                $create($model, null);
                $tenantRow[$model] = $create($model, self::TENANT_A);
            } else {
                $tenantRow[$model] = $create($model, self::TENANT_A);
                $create($model, null);
            }
        }
        $this->actingAs(User::factory()->create(['tenant_id' => self::TENANT_A]));

        foreach (range(1, 3) as $attempt) {
            $this->assertSame($tenantRow[KeystoneRole::class]->id, app(RoleServiceInterface::class)->findByName('editor')->id);
            $this->assertSame($tenantRow[KeystonePermission::class]->id, app(PermissionServiceInterface::class)->findByName('editor')->id);
        }
    }

    #[Test]
    #[DataProvider('insertionOrders')]
    public function without_a_reference_tenant_the_global_row_is_preferred(bool $globalFirst)
    {
        config(['keystone.features.multi_tenant' => true]);

        foreach ([KeystoneRole::class, KeystonePermission::class] as $model) {
            if ($globalFirst) {
                $global[$model] = $model::create(['name' => 'editor']);
                $model::create(['name' => 'editor', 'tenant_id' => self::TENANT_A]);
            } else {
                $model::create(['name' => 'editor', 'tenant_id' => self::TENANT_A]);
                $global[$model] = $model::create(['name' => 'editor']);
            }
        }

        // No authenticated user and no tenant argument (a job or command)
        $this->assertSame($global[KeystoneRole::class]->id, app(RoleServiceInterface::class)->findByName('editor')->id);
        $this->assertSame($global[KeystonePermission::class]->id, app(PermissionServiceInterface::class)->findByName('editor')->id);
    }

    #[Test]
    public function single_tenant_lookup_ignores_the_tenant_argument()
    {
        config(['keystone.features.multi_tenant' => false]);
        $role = KeystoneRole::create(['name' => 'admin']);

        $this->assertSame($role->id, app(RoleServiceInterface::class)->findByName('admin', 'web', self::TENANT_A)->id);
    }
}
