<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Search;

/**
 * Immutable description of a skill search, handed to the active resolver.
 */
final readonly class SkillSearchCriteria
{
    /**
     * @param  string  $term  The normalized search term.
     * @param  bool  $partial  Match names/aliases containing the term instead of equal to it.
     * @param  bool  $aliases  Match aliases in addition to canonical names.
     * @param  int|null  $categoryId  Restrict to one category.
     * @param  string|null  $locale  Only match aliases for this locale (or locale-less aliases).
     * @param  bool  $includeInactive  Include skills where is_active is false.
     * @param  bool  $withAliases  Eager load aliases on the returned skills.
     */
    public function __construct(
        public string $term,
        public bool $partial,
        public bool $aliases,
        public ?int $categoryId = null,
        public ?string $locale = null,
        public bool $includeInactive = false,
        public bool $withAliases = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->term === '';
    }
}
