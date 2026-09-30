<?php

namespace BSPDX\Keystone\Exceptions;

/**
 * Thrown when a permission with the same name, guard, and tenant already exists.
 */
class PermissionAlreadyExists extends KeystoneException
{
    public static function create(string $name, string $guard, ?string $tenantId): self
    {
        $scope = $tenantId === null ? 'globally' : "in tenant '{$tenantId}'";

        return new self("A permission '{$name}' already exists for guard '{$guard}' {$scope}.");
    }
}
