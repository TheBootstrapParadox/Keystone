<?php

namespace BSPDX\Keystone\Support;

/**
 * Resolves the guard Keystone uses whenever a caller does not name one.
 *
 * @internal
 */
final class DefaultGuard
{
    /**
     * The given guard, or the application's default auth guard when null.
     */
    public static function resolve(?string $guard = null): string
    {
        return $guard ?? (string) config('auth.defaults.guard', 'web');
    }
}
