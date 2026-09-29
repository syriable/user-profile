<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Search;

/**
 * Immutable description of a language search, handed to the active resolver.
 */
final readonly class LanguageSearchCriteria
{
    /**
     * @param  string  $term  The normalized search term.
     * @param  bool  $partial  Match names containing the term instead of equal to it.
     * @param  bool  $includeInactive  Include languages where is_active is false.
     */
    public function __construct(
        public string $term,
        public bool $partial,
        public bool $includeInactive = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->term === '';
    }
}
