<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class IncompatibleProfileOwner extends LogicException implements UserProfileException
{
    public static function keyType(Model $owner, string $expected): self
    {
        return new self(sprintf(
            'The profile owner [%s] uses [%s] primary keys, but user-profile.owner_key_type is [%s]. '
            .'All profile owner models must share the configured key type because profileable_id has a single column type.',
            $owner::class,
            $owner->getKeyType(),
            $expected,
        ));
    }

    public static function keyValue(Model $owner, string $expected): self
    {
        return new self(sprintf(
            'The primary key of profile owner [%s] is not a valid [%s] value, which user-profile.owner_key_type requires.',
            $owner::class,
            $expected,
        ));
    }
}
