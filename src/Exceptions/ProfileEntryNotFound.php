<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ProfileEntryNotFound extends RuntimeException implements UserProfileException
{
    public static function onProfile(Model $user, Model $entry): self
    {
        return new self(sprintf(
            'User [%s] does not have %s [%s] on their profile.',
            self::key($user),
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

    private static function key(Model $model): string
    {
        $key = $model->getKey();

        return is_scalar($key) ? (string) $key : get_debug_type($key);
    }
}
