<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Syriable\UserProfile\Exceptions\IncompatibleProfileOwner;

/**
 * Guards the polymorphic owner contract: every owner model must use the
 * configured key type, because `profileable_id` has one native column type.
 *
 * @internal
 */
final class ProfileOwner
{
    /**
     * Owner classes whose key type has already been checked, per key type.
     *
     * @var array<string, true>
     */
    private static array $checked = [];

    public static function ensureCompatible(Model $owner): void
    {
        $type = PackageConfig::ownerKeyType();
        $cacheKey = $owner::class.'|'.$type;

        if (! isset(self::$checked[$cacheKey])) {
            if ($owner->getKeyType() !== ($type === 'int' ? 'int' : 'string')) {
                throw IncompatibleProfileOwner::keyType($owner, $type);
            }

            self::$checked[$cacheKey] = true;
        }

        $key = $owner->getKey();

        if ($key !== null && ! self::keyMatches($key, $type)) {
            throw IncompatibleProfileOwner::keyValue($owner, $type);
        }
    }

    private static function keyMatches(mixed $key, string $type): bool
    {
        return match ($type) {
            'int' => is_int($key) || (is_string($key) && ctype_digit($key)),
            'uuid' => is_string($key) && Str::isUuid($key),
            default => is_string($key) && Str::isUlid($key),
        };
    }
}
