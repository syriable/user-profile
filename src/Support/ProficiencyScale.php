<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Syriable\UserProfile\Enums\ProficiencyType;
use Syriable\UserProfile\Exceptions\InvalidProficiency;

/**
 * An ordered list of proficiency levels. The position of a level in the list
 * is its rank, so levels can be sorted and compared without storing numbers.
 */
final readonly class ProficiencyScale
{
    /**
     * @param  list<string>  $levels  Ordered from lowest to highest.
     */
    public function __construct(
        public ProficiencyType $type,
        public array $levels,
        public ?string $default = null,
        public bool $required = false,
    ) {
        if ($levels === [] || count($levels) !== count(array_unique($levels))) {
            throw new InvalidArgumentException("The {$type->value} proficiency scale must contain at least one level and no duplicates.");
        }

        if ($default !== null && ! in_array($default, $levels, true)) {
            throw new InvalidArgumentException("The default {$type->value} proficiency [{$default}] is not a configured level.");
        }
    }

    public static function fromConfig(ProficiencyType $type): self
    {
        $levels = [];

        foreach ((array) config("user-profile.{$type->value}.proficiency_levels", []) as $level) {
            if (! is_string($level) || $level === '') {
                throw new InvalidArgumentException("The {$type->value} proficiency levels must be non-empty strings.");
            }

            $levels[] = $level;
        }

        $default = config("user-profile.{$type->value}.default_proficiency");

        return new self(
            type: $type,
            levels: $levels,
            default: is_string($default) ? $default : null,
            required: (bool) config("user-profile.{$type->value}.require_proficiency", false),
        );
    }

    public function has(?string $level): bool
    {
        return $level !== null && in_array($level, $this->levels, true);
    }

    /**
     * Zero-based rank of a level; higher means more proficient.
     */
    public function rank(string $level): int
    {
        $rank = array_search($level, $this->levels, true);

        if ($rank === false) {
            throw InvalidProficiency::unknown($this, $level);
        }

        return $rank;
    }

    /**
     * Spaceship comparison of two levels: -1, 0 or 1.
     */
    public function compare(string $a, string $b): int
    {
        return $this->rank($a) <=> $this->rank($b);
    }

    /**
     * All levels ranked at or above the given level.
     *
     * @return list<string>
     */
    public function atLeast(string $level): array
    {
        return array_slice($this->levels, $this->rank($level));
    }

    public function label(string $level): string
    {
        $key = "user-profile::proficiency.{$this->type->value}.{$level}";
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($level);
    }

    /**
     * Level => label pairs, ready for select inputs.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->levels as $level) {
            $options[$level] = $this->label($level);
        }

        return $options;
    }

    /**
     * Applies the default level and validates the result.
     *
     * @throws InvalidProficiency
     */
    public function resolve(?string $level): ?string
    {
        $level ??= $this->default;

        if ($level === null) {
            if ($this->required) {
                throw InvalidProficiency::missing($this);
            }

            return null;
        }

        if (! $this->has($level)) {
            throw InvalidProficiency::unknown($this, $level);
        }

        return $level;
    }
}
