<?php

namespace BSPDX\Keystone\Support;

use BSPDX\Keystone\Contracts\KeystoneUser;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Resolves the application's user model.
 *
 * @internal
 */
final class UserModel
{
    /**
     * The configured user model class: keystone.user.model, then
     * auth.providers.users.model.
     *
     * @return class-string<Model&KeystoneUser>
     *
     * @throws LogicException when neither config key names a class, or the
     *                        class does not implement KeystoneUser
     */
    public static function className(): string
    {
        $class = config('keystone.user.model') ?? config('auth.providers.users.model');

        if (! is_string($class) || $class === '') {
            throw new LogicException(
                'Keystone could not determine the user model. Set keystone.user.model or auth.providers.users.model.'
            );
        }

        if (! is_subclass_of($class, KeystoneUser::class)) {
            throw new LogicException(
                "Keystone's user model {$class} must implement ".KeystoneUser::class.'. Add `implements KeystoneUser` alongside `use HasKeystone;`.'
            );
        }

        /** @var class-string<Model&KeystoneUser> $class */
        return $class;
    }

    /**
     * A new, empty instance of the configured user model.
     */
    public static function instance(): Model&KeystoneUser
    {
        $class = self::className();

        return new $class;
    }
}
