<?php

namespace BSPDX\Keystone\Exceptions;

/**
 * Thrown when a role with the same name, guard, and tenant already exists.
 */
class RoleAlreadyExists extends KeystoneException
{
    public static function create(string $name, string $guard, ?string $tenantId): self
    {
        $scope = $tenantId === null ? 'globally' : "in tenant '{$tenantId}'";

        return new self("A role '{$name}' already exists for guard '{$guard}' {$scope}.");
    }

    /**
     * A duplicate is a conflict with an existing row.
     */
    public function status(): int
    {
        return 409;
    }
}
