<?php

namespace BSPDX\Keystone\Exceptions;

/**
 * Thrown when a tenant-scoped role would take the reserved super-admin name.
 */
class ReservedRoleName extends KeystoneException
{
    public static function forTenant(string $name): self
    {
        return new self("The role name '{$name}' is reserved for the global super-admin role and cannot be used in a tenant.");
    }

    /**
     * The name itself is not acceptable.
     */
    public function status(): int
    {
        return 422;
    }
}
