<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Search\SkillSearchCriteria;

/**
 * Turns search criteria into a query for canonical skills.
 *
 * Implementations must return each canonical skill at most once and apply a
 * deterministic order, so the query can be paginated safely.
 */
interface SkillSearchResolver
{
    /**
     * @return Builder<Skill>
     */
    public function search(SkillSearchCriteria $criteria): Builder;
}
