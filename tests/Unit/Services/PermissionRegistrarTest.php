<?php

namespace Tests\Unit\Services;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\PermissionRegistrar;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionRegistrarTest extends TestCase
{
    #[Test]
    public function it_uses_cache_expiration_from_keystone_config(): void
    {
        config(['keystone.rbac.cache_expiration' => 12345]);

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('remember')
            ->once()
            ->with('keystone.permissions.all.v2', 12345, Mockery::type('Closure'))
            ->andReturn(new Collection);

        $registrar = new PermissionRegistrar($cache);

        $registrar->getAllPermissionNames();
    }

    #[Test]
    public function it_reads_cache_expiration_dynamically_after_construction(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('remember')
            ->once()
            ->with('keystone.permissions.all.v2', 555, Mockery::type('Closure'))
            ->andReturn(new Collection);

        // Construct BEFORE changing config to prove the TTL is read at call
        // time, not captured at construction (the registrar is a singleton).
        $registrar = new PermissionRegistrar($cache);

        config(['keystone.rbac.cache_expiration' => 555]);

        $registrar->getAllPermissionNames();
    }

    #[Test]
    public function it_falls_back_to_default_expiration_when_config_missing(): void
    {
        config(['keystone.rbac.cache_expiration' => null]);

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('remember')
            ->once()
            ->with('keystone.permissions.all.v2', 86400, Mockery::type('Closure'))
            ->andReturn(new Collection);

        $registrar = new PermissionRegistrar($cache);

        $registrar->getAllPermissionNames();
    }

    #[Test]
    public function creating_a_permission_invalidates_the_cached_list(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->getAllPermissionNames(); // warm the cache

        KeystonePermission::create(['name' => 'publish-posts']);

        $this->assertTrue($registrar->permissionExists('publish-posts'));
    }

    #[Test]
    public function deleting_a_permission_invalidates_the_cached_list(): void
    {
        $permission = KeystonePermission::create(['name' => 'edit-posts']);
        $registrar = app(PermissionRegistrar::class);
        $this->assertTrue($registrar->permissionExists('edit-posts')); // warm the cache

        $permission->delete();

        $this->assertFalse($registrar->permissionExists('edit-posts'));
        $this->assertNotContains('edit-posts', $registrar->getAllPermissionNames());
    }

    #[Test]
    public function renaming_a_permission_invalidates_the_cached_list(): void
    {
        $permission = KeystonePermission::create(['name' => 'edit-posts']);
        $registrar = app(PermissionRegistrar::class);
        $registrar->getAllPermissionNames(); // warm the cache

        $permission->update(['name' => 'modify-posts']);

        $names = $registrar->getAllPermissionNames();
        $this->assertContains('modify-posts', $names);
        $this->assertNotContains('edit-posts', $names);
    }

    #[Test]
    public function permission_created_through_the_service_is_visible_immediately(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->getAllPermissionNames(); // warm the cache

        app(PermissionServiceInterface::class)->create('archive-posts');

        $this->assertTrue($registrar->permissionExists('archive-posts'));
    }
}
