<?php

namespace BSPDX\Keystone\Exceptions;

/**
 * Thrown when something tries to delete the configured super-admin role.
 */
class CannotDeleteSuperAdminRole extends KeystoneException
{
    public function __construct()
    {
        parent::__construct('Cannot delete the super admin role.');
    }

    /**
     * Nobody may delete the super-admin role.
     */
    public function status(): int
    {
        return 403;
    }
}
