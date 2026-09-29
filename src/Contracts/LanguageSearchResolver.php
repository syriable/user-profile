<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Search\LanguageSearchCriteria;

/**
 * Turns search criteria into a query for catalog languages.
 *
 * Implementations must return each language at most once and apply a
 * deterministic order, so the query can be paginated safely.
 */
interface LanguageSearchResolver
{
    /**
     * @return Builder<Language>
     */
    public function search(LanguageSearchCriteria $criteria): Builder;
}
