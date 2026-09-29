<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Search;

use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Contracts\LanguageSearchResolver;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Support\Normalizer;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Portable SQL search over language names, native names and codes.
 *
 * Codes (code, ISO 639-1, ISO 639-3) always match exactly; names match
 * exactly or partially depending on the criteria.
 *
 * Ordering (deterministic):
 *   0. a code equals the term
 *   1. name or native name equals the term
 *   2. name or native name starts with the term (partial searches)
 *   3. any other partial match
 * then by name and primary key.
 *
 * All raw SQL is literal; search values are always passed as bindings.
 */
class DatabaseLanguageSearchResolver implements LanguageSearchResolver
{
    private const string CODE_EQUALS = 'lower(code) = ? or iso_639_1 = ? or iso_639_3 = ?';

    private const string NAME_EQUALS = 'normalized_name = ? or normalized_native_name = ?';

    private const string NAME_LIKE = "normalized_name like ? escape '!' or normalized_native_name like ? escape '!'";

    public function search(LanguageSearchCriteria $criteria): Builder
    {
        $model = PackageConfig::model('language', Language::class);

        /** @var Builder<Language> $query */
        $query = $model::query();

        if ($criteria->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $language = $query->getModel();
        $term = $criteria->term;
        $contains = '%'.Normalizer::escapeLike($term).'%';
        $prefix = Normalizer::escapeLike($term).'%';

        $query->where(function (Builder $where) use ($criteria, $term, $contains): void {
            $where->whereRaw('('.self::CODE_EQUALS.')', [$term, $term, $term]);

            if ($criteria->partial) {
                $where->orWhereRaw('('.self::NAME_LIKE.')', [$contains, $contains]);
            } else {
                $where->orWhereRaw('('.self::NAME_EQUALS.')', [$term, $term]);
            }
        });

        if (! $criteria->includeInactive) {
            $query->where($language->qualifyColumn('is_active'), true);
        }

        $query->orderByRaw(
            'case when '.self::CODE_EQUALS.' then 0 when '.self::NAME_EQUALS.' then 1 when '.self::NAME_LIKE.' then 2 else 3 end',
            [$term, $term, $term, $term, $term, $prefix, $prefix],
        );

        return $query->orderBy($language->qualifyColumn('normalized_name'))
            ->orderBy($language->getQualifiedKeyName());
    }
}
