<?php

namespace Tests\Unit\Services;

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
            ->with('keystone.permissions.all', 12345, Mockery::type('Closure'))
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
            ->with('keystone.permissions.all', 555, Mockery::type('Closure'))
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
            ->with('keystone.permissions.all', 86400, Mockery::type('Closure'))
            ->andReturn(new Collection);

        $registrar = new PermissionRegistrar($cache);

        $registrar->getAllPermissionNames();
    }
}
