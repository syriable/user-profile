<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Syriable\UserProfile\Enums\Feature;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Query scopes that filter profile owners by their skills and languages.
 *
 * Every scope adds one EXISTS (or NOT EXISTS) condition on the owner's pivot
 * rows. The condition includes the owner's morph type, so owners of
 * different types that share an ID never match each other. Owners are never
 * duplicated, and the result stays an Eloquent query that can be paginated.
 *
 * Chained scopes combine with AND. For OR, use the "any" scopes, or group
 * scopes in a closure: ->where(fn ($q) => $q->whereSkill('php')->orWhere(...)).
 *
 * Skill and language terms are resolved to catalog IDs first, with
 * UserProfile::resolveSkills() / resolveLanguages(), and the owner query then
 * filters by ID. A term naming several catalog entries (an ambiguous alias)
 * matches any of them; an unknown term matches no entries.
 *
 * @mixin Model
 */
trait FiltersProfileOwners
{
    /**
     * Owners who have the skill.
     *
     * @param  Builder<static>  $query
     * @param  string|null  $atLeast  Minimum proficiency on the skill scale.
     * @param  bool|null  $primary  Restrict to (or exclude) the owner's primary skill.
     */
    public function scopeWhereSkill(Builder $query, Skill|int|string $skill, ?string $atLeast = null, ?bool $primary = null): void
    {
        $this->whereProfileSkill($query, $this->profileSkillIds([$skill]), $atLeast, $primary);
    }

    /**
     * Owners who have at least one of the skills.
     *
     * @param  Builder<static>  $query
     * @param  array<int, Skill|int|string>  $skills  An empty list matches no owners.
     */
    public function scopeWhereAnySkill(Builder $query, array $skills, ?string $atLeast = null): void
    {
        $this->whereProfileSkill($query, $this->profileSkillIds($skills), $atLeast);
    }

    /**
     * Owners who have every one of the skills.
     *
     * @param  Builder<static>  $query
     * @param  array<int, Skill|int|string>  $skills  An empty list adds no condition.
     */
    public function scopeWhereAllSkills(Builder $query, array $skills, ?string $atLeast = null): void
    {
        foreach ($skills as $skill) {
            $this->whereProfileSkill($query, $this->profileSkillIds([$skill]), $atLeast);
        }
    }

    /**
     * Owners who do not have the skill.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithoutSkill(Builder $query, Skill|int|string $skill): void
    {
        $this->whereProfileSkill($query, $this->profileSkillIds([$skill]), exists: false);
    }

    /**
     * Owners who speak the language.
     *
     * A proficiency threshold is also met by native speakers, whose native
     * flag is separate from the proficiency scale.
     *
     * @param  Builder<static>  $query
     * @param  string|null  $atLeast  Minimum proficiency on the language scale.
     * @param  bool|null  $native  Restrict to native (true) or non-native (false) speakers.
     * @param  bool|null  $primary  Restrict to (or exclude) the owner's primary language.
     */
    public function scopeWhereLanguage(
        Builder $query,
        Language|int|string $language,
        ?string $atLeast = null,
        ?bool $native = null,
        ?bool $primary = null,
    ): void {
        $this->whereProfileLanguage($query, $this->profileLanguageIds([$language]), $atLeast, $native, $primary);
    }

    /**
     * Owners who speak at least one of the languages.
     *
     * @param  Builder<static>  $query
     * @param  array<int, Language|int|string>  $languages  An empty list matches no owners.
     */
    public function scopeWhereAnyLanguage(Builder $query, array $languages, ?string $atLeast = null): void
    {
        $this->whereProfileLanguage($query, $this->profileLanguageIds($languages), $atLeast);
    }

    /**
     * Owners who speak every one of the languages.
     *
     * @param  Builder<static>  $query
     * @param  array<int, Language|int|string>  $languages  An empty list adds no condition.
     */
    public function scopeWhereAllLanguages(Builder $query, array $languages, ?string $atLeast = null): void
    {
        foreach ($languages as $language) {
            $this->whereProfileLanguage($query, $this->profileLanguageIds([$language]), $atLeast);
        }
    }

    /**
     * Owners who do not speak the language.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithoutLanguage(Builder $query, Language|int|string $language): void
    {
        $this->whereProfileLanguage($query, $this->profileLanguageIds([$language]), exists: false);
    }

    /**
     * @param  array<int, Skill|int|string>  $skills
     * @return list<int>
     */
    protected function profileSkillIds(array $skills): array
    {
        $ids = [];

        foreach ($skills as $skill) {
            array_push($ids, ...match (true) {
                $skill instanceof Skill => [$skill->id],
                is_int($skill) => [$skill],
                default => array_map(intval(...), UserProfile::resolveSkills($skill)->modelKeys()),
            });
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, Language|int|string>  $languages
     * @return list<int>
     */
    protected function profileLanguageIds(array $languages): array
    {
        $ids = [];

        foreach ($languages as $language) {
            array_push($ids, ...match (true) {
                $language instanceof Language => [$language->id],
                is_int($language) => [$language],
                default => array_map(intval(...), UserProfile::resolveLanguages($language)->modelKeys()),
            });
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<int>  $skillIds
     */
    private function whereProfileSkill(Builder $query, array $skillIds, ?string $atLeast = null, ?bool $primary = null, bool $exists = true): void
    {
        PackageConfig::ensureFeatureEnabled(Feature::Skills);

        // Validate the level before building SQL; an unknown level throws.
        $levels = $atLeast === null ? null : UserProfile::skillProficiency()->atLeast($atLeast);

        $constrain = function (Builder $entries) use ($skillIds, $levels, $primary): void {
            $table = $entries->getModel()->getTable();
            $where = $entries->getQuery();

            $where->whereIn("{$table}.skill_id", $skillIds);

            if ($levels !== null) {
                $where->whereIn("{$table}.proficiency_level", $levels);
            }

            if ($primary !== null) {
                $where->where("{$table}.is_primary", $primary);
            }
        };

        $exists
            ? $query->whereHas('profileSkills', $constrain)
            : $query->whereDoesntHave('profileSkills', $constrain);
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<int>  $languageIds
     */
    private function whereProfileLanguage(
        Builder $query,
        array $languageIds,
        ?string $atLeast = null,
        ?bool $native = null,
        ?bool $primary = null,
        bool $exists = true,
    ): void {
        PackageConfig::ensureFeatureEnabled(Feature::Languages);

        $levels = $atLeast === null ? null : UserProfile::languageProficiency()->atLeast($atLeast);

        $constrain = function (Builder $entries) use ($languageIds, $levels, $native, $primary): void {
            $table = $entries->getModel()->getTable();
            $where = $entries->getQuery();

            $where->whereIn("{$table}.language_id", $languageIds);

            if ($levels !== null) {
                $where->where(fn (QueryBuilder $threshold) => $threshold
                    ->where("{$table}.is_native", true)
                    ->orWhereIn("{$table}.proficiency_level", $levels));
            }

            if ($native !== null) {
                $where->where("{$table}.is_native", $native);
            }

            if ($primary !== null) {
                $where->where("{$table}.is_primary", $primary);
            }
        };

        $exists
            ? $query->whereHas('profileLanguages', $constrain)
            : $query->whereDoesntHave('profileLanguages', $constrain);
    }
}
