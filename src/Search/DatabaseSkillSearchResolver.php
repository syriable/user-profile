<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Syriable\UserProfile\Contracts\SkillSearchResolver;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillAlias;
use Syriable\UserProfile\Support\Normalizer;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Portable SQL search over canonical skill names and aliases.
 *
 * Matching runs against the stored `normalized_*` columns, so it is case- and
 * whitespace-insensitive on every database. Alias matches are expressed with
 * EXISTS sub-queries rather than joins, so a skill with several matching
 * aliases is still returned once.
 *
 * Ordering (deterministic), by the first condition that holds:
 *   1. canonical name equals the term
 *   2. an alias equals the term
 *   3. canonical name starts with the term (partial searches)
 *   4. an alias starts with the term (partial searches)
 *   5. any other partial match
 * then by canonical name and primary key.
 *
 * All raw SQL is literal; search values are always passed as bindings.
 */
class DatabaseSkillSearchResolver implements SkillSearchResolver
{
    public function search(SkillSearchCriteria $criteria): Builder
    {
        $model = PackageConfig::model('skill', Skill::class);

        /** @var Builder<Skill> $query */
        $query = $model::query();

        if ($criteria->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $skill = $query->getModel();
        $term = $criteria->term;
        $contains = '%'.Normalizer::escapeLike($term).'%';
        $prefix = Normalizer::escapeLike($term).'%';

        $query->where(function (Builder $where) use ($query, $criteria, $skill, $term, $contains): void {
            if ($criteria->partial) {
                $where->whereRaw("normalized_name like ? escape '!'", [$contains]);
            } else {
                $where->where($skill->qualifyColumn('normalized_name'), $term);
            }

            if ($criteria->aliases) {
                $where->orWhereExists($this->aliasQuery($query, $criteria, $criteria->partial, $criteria->partial ? $contains : $term));
            }
        });

        if (! $criteria->includeInactive) {
            $query->where($skill->qualifyColumn('is_active'), true);
        }

        if ($criteria->categoryId !== null) {
            $query->where($skill->qualifyColumn('category_id'), $criteria->categoryId);
        }

        // Sorting by each condition in turn is equivalent to ranking by the
        // first condition that holds.
        $query->orderByRaw('case when normalized_name = ? then 0 else 1 end', [$term]);

        if ($criteria->aliases) {
            $query->orderByDesc($this->aliasMatched($this->aliasQuery($query, $criteria, false, $term)));
        }

        if ($criteria->partial) {
            $query->orderByRaw("case when normalized_name like ? escape '!' then 0 else 1 end", [$prefix]);

            if ($criteria->aliases) {
                $query->orderByDesc($this->aliasMatched($this->aliasQuery($query, $criteria, true, $prefix)));
            }
        }

        $query->orderBy($skill->qualifyColumn('normalized_name'))
            ->orderBy($skill->getQualifiedKeyName());

        if ($criteria->withAliases) {
            $query->with(['aliases' => function (Relation $aliases) use ($criteria): void {
                $aliases->orderBy('normalized_alias');

                if ($criteria->locale !== null) {
                    $aliases->getQuery()->getQuery()->where(fn (QueryBuilder $locale) => $locale
                        ->whereNull('locale')
                        ->orWhere('locale', $criteria->locale));
                }
            }]);
        }

        return $query;
    }

    /**
     * A correlated sub-query over the aliases of the outer skill.
     *
     * @param  Builder<Skill>  $query
     */
    protected function aliasQuery(Builder $query, SkillSearchCriteria $criteria, bool $like, string $value): QueryBuilder
    {
        $aliasTable = (new (PackageConfig::model('skill_alias', SkillAlias::class)))->getTable();

        $aliases = $query->getQuery()->newQuery()
            ->from($aliasTable)
            ->whereColumn("{$aliasTable}.skill_id", $query->getModel()->getQualifiedKeyName());

        if ($criteria->locale !== null) {
            $aliases->where(fn (QueryBuilder $locale) => $locale
                ->whereNull("{$aliasTable}.locale")
                ->orWhere("{$aliasTable}.locale", $criteria->locale));
        }

        return $like
            ? $aliases->whereRaw("normalized_alias like ? escape '!'", [$value])
            : $aliases->where("{$aliasTable}.normalized_alias", $value);
    }

    /**
     * A portable scalar sub-query: 1 when any alias matches, otherwise 0.
     */
    protected function aliasMatched(QueryBuilder $aliases): QueryBuilder
    {
        return $aliases->selectRaw('case when count(*) > 0 then 1 else 0 end');
    }
}
