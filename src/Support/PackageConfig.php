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
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        $model = config('user-profile.user.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new InvalidArgumentException('The [user-profile.user.model] configuration value must be an Eloquent model class.');
        }

        return $model;
    }

    public static function userKeyType(): string
    {
        $type = config('user-profile.user.key_type', 'int');

        if (! in_array($type, ['int', 'uuid', 'ulid'], true)) {
            throw new InvalidArgumentException('The [user-profile.user.key_type] configuration value must be one of: int, uuid, ulid.');
        }

        return $type;
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
