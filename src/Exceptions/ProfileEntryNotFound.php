<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ProfileEntryNotFound extends RuntimeException implements UserProfileException
{
    public static function onProfile(Model $owner, Model $entry): self
    {
        return new self(sprintf(
            'Profile owner [%s] does not have %s [%s] on its profile.',
            self::owner($owner),
            class_basename($entry),
            self::key($entry),
        ));
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function inCatalog(string $model, int|string $identifier): self
    {
        return new self(sprintf('No %s matches [%s].', class_basename($model), (string) $identifier));
    }

    private static function owner(Model $owner): string
    {
        return $owner->getMorphClass().':'.self::key($owner);
    }

    private static function key(Model $model): string
    {
        $key = $model->getKey();

        return is_scalar($key) ? (string) $key : get_debug_type($key);
    }
}
