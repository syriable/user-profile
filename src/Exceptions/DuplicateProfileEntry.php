<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class DuplicateProfileEntry extends RuntimeException implements UserProfileException
{
    public static function for(Model $user, Model $entry): self
    {
        return new self(sprintf(
            'User [%s] already has %s [%s] on their profile.',
            self::key($user),
            class_basename($entry),
            self::key($entry),
        ));
    }

    private static function key(Model $model): string
    {
        $key = $model->getKey();

        return is_scalar($key) ? (string) $key : get_debug_type($key);
    }
}
