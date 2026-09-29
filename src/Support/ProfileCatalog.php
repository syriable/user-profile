<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Syriable\UserProfile\Exceptions\DuplicateProfileEntry;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Exceptions\ProfileEntryNotFound;

/**
 * Shared, transactional write logic for the owner ⇄ catalog pivot tables
 * (profile_languages, profile_skills).
 *
 * @internal
 */
final class ProfileCatalog
{
    /**
     * @template TRelated of Model
     * @template TDeclaring of Model
     * @template TPivot of Pivot
     *
     * @param  BelongsToMany<TRelated, TDeclaring, TPivot>  $relation
     * @param  array<string, mixed>  $attributes
     * @return TPivot
     */
    public static function attach(BelongsToMany $relation, Model $entry, array $attributes): Pivot
    {
        $owner = $relation->getParent();

        return $owner->getConnection()->transaction(function () use ($relation, $owner, $entry, $attributes): Pivot {
            if (self::has($relation, $entry)) {
                throw DuplicateProfileEntry::for($owner, $entry);
            }

            if (($attributes['is_primary'] ?? false) === true) {
                self::clearPrimary($relation);
            }

            try {
                $relation->attach($entry->getKey(), $attributes);
            } catch (UniqueConstraintViolationException) {
                throw DuplicateProfileEntry::for($owner, $entry);
            }

            return self::pivot($relation, $entry);
        });
    }

    /**
     * @template TRelated of Model
     * @template TDeclaring of Model
     * @template TPivot of Pivot
     *
     * @param  BelongsToMany<TRelated, TDeclaring, TPivot>  $relation
     * @param  array<string, mixed>  $attributes
     * @return TPivot
     */
    public static function update(BelongsToMany $relation, Model $entry, array $attributes): Pivot
    {
        $owner = $relation->getParent();

        return $owner->getConnection()->transaction(function () use ($relation, $owner, $entry, $attributes): Pivot {
            if (! self::has($relation, $entry)) {
                throw ProfileEntryNotFound::onProfile($owner, $entry);
            }

            if (($attributes['is_primary'] ?? false) === true) {
                self::clearPrimary($relation);
            }

            $relation->updateExistingPivot($entry->getKey(), $attributes);

            return self::pivot($relation, $entry);
        });
    }

    /**
     * @template TRelated of Model
     * @template TDeclaring of Model
     * @template TPivot of Pivot
     *
     * @param  BelongsToMany<TRelated, TDeclaring, TPivot>  $relation
     */
    public static function detach(BelongsToMany $relation, Model $entry): bool
    {
        return $relation->detach($entry->getKey()) > 0;
    }

    /**
     * @template TRelated of Model
     * @template TDeclaring of Model
     * @template TPivot of Pivot
     *
     * @param  BelongsToMany<TRelated, TDeclaring, TPivot>  $relation
     */
    public static function has(BelongsToMany $relation, Model $entry): bool
    {
        return $relation->newPivotQuery()
            ->where($relation->getRelatedPivotKeyName(), $entry->getKey())
            ->exists();
    }

    /**
     * Validates and normalizes attributes written to a owner ⇄ catalog pivot.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws InvalidProficiency
     */
    public static function attributes(array $attributes, array $allowed, ProficiencyScale $scale): array
    {
        $unknown = array_diff(array_keys($attributes), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown profile attribute(s) [%s]. Allowed: %s.',
                implode(', ', $unknown),
                implode(', ', $allowed),
            ));
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = match ($key) {
                'proficiency_level' => $scale->resolve(self::nullableString($key, $value)),
                'years_of_experience' => self::yearsOfExperience($value),
                default => self::boolean($key, $value),
            };
        }

        return $attributes;
    }

    private static function nullableString(string $key, mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException("The [{$key}] profile attribute must be a string or null.");
        }

        return $value;
    }

    private static function yearsOfExperience(mixed $value): ?int
    {
        if ($value !== null && (! is_int($value) || $value < 0 || $value > 100)) {
            throw new InvalidArgumentException('Years of experience must be an integer between 0 and 100, or null.');
        }

        return $value;
    }

    private static function boolean(string $key, mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException("The [{$key}] profile attribute must be a boolean.");
        }

        return $value;
    }

    /**
     * @template TRelated of Model
     * @template TDeclaring of Model
     * @template TPivot of Pivot
     *
     * @param  BelongsToMany<TRelated, TDeclaring, TPivot>  $relation
     */
    private static function clearPrimary(BelongsToMany $relation): void
    {
        $relation->newPivotQuery()->update(['is_primary' => false]);
    }

    /**
     * @template TRelated of Model
     * @template TDeclaring of Model
     * @template TPivot of Pivot
     *
     * @param  BelongsToMany<TRelated, TDeclaring, TPivot>  $relation
     * @return TPivot
     */
    private static function pivot(BelongsToMany $relation, Model $entry): Pivot
    {
        /** @var Model $related */
        $related = (clone $relation)->whereKey($entry->getKey())->firstOrFail();

        /** @var TPivot */
        return $related->getRelation($relation->getPivotAccessor());
    }
}
