<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Syriable\UserProfile\Enums\Feature;
use Syriable\UserProfile\Exceptions\FeatureDisabled;

/**
 * Typed access to the package configuration.
 *
 * @internal
 */
final class PackageConfig
{
    public static function table(string $key): string
    {
        $table = config("user-profile.table_names.{$key}");

        if (! is_string($table) || $table === '') {
            throw new InvalidArgumentException("No table name is configured for [user-profile.table_names.{$key}].");
        }

        return $table;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $base
     * @return class-string<TModel>
     */
    public static function model(string $key, string $base): string
    {
        $model = config("user-profile.models.{$key}", $base);

        if (! is_string($model) || ! is_a($model, $base, true)) {
            throw new InvalidArgumentException("The model configured for [user-profile.models.{$key}] must extend [{$base}].");
        }

        return $model;
    }

    /**
     * The single primary key type shared by every profile owner model.
     *
     * @return 'int'|'uuid'|'ulid'
     */
    public static function ownerKeyType(): string
    {
        $type = config('user-profile.owner_key_type', 'int');

        return match ($type) {
            'int', 'uuid', 'ulid' => $type,
            default => throw new InvalidArgumentException('The [user-profile.owner_key_type] configuration value must be one of: int, uuid, ulid.'),
        };
    }

    public static function featureEnabled(Feature $feature): bool
    {
        return (bool) config("user-profile.features.{$feature->value}", true);
    }

    public static function ensureFeatureEnabled(Feature $feature): void
    {
        if (! self::featureEnabled($feature)) {
            throw FeatureDisabled::for($feature);
        }
    }

    public static function validationEnabled(): bool
    {
        return (bool) config('user-profile.validation.enabled', true);
    }

    public static function boolean(string $key, bool $default): bool
    {
        return (bool) config("user-profile.{$key}", $default);
    }

    public static function integer(string $key, int $default): int
    {
        $value = config("user-profile.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
