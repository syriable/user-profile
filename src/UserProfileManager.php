<?php

declare(strict_types=1);

namespace Syriable\UserProfile;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Syriable\UserProfile\Contracts\LanguageSearchResolver;
use Syriable\UserProfile\Contracts\SkillSearchResolver;
use Syriable\UserProfile\Enums\Feature;
use Syriable\UserProfile\Enums\ProficiencyType;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillCategory;
use Syriable\UserProfile\Search\LanguageSearchCriteria;
use Syriable\UserProfile\Search\SkillSearchCriteria;
use Syriable\UserProfile\Support\Normalizer;
use Syriable\UserProfile\Support\PackageConfig;
use Syriable\UserProfile\Support\ProficiencyScale;

/**
 * Entry point behind the UserProfile facade: catalog search, proficiency
 * scales and extension registration.
 */
final readonly class UserProfileManager
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * Searches the skill catalog by canonical name and aliases.
     *
     * Returns a query builder so callers decide how to execute it: ->get(),
     * ->paginate(), ->limit(10)->get(), ...
     *
     * @param  bool|null  $partial  Null uses the configured default.
     * @return Builder<Skill>
     */
    public function searchSkills(
        string $term,
        SkillCategory|int|null $category = null,
        ?bool $partial = null,
        ?string $locale = null,
        bool $includeInactive = false,
        bool $withAliases = false,
    ): Builder {
        $term = Normalizer::normalize($term);

        return $this->container->make(SkillSearchResolver::class)->search(new SkillSearchCriteria(
            term: $term,
            partial: $this->usesPartialMatching($term, $partial),
            aliases: PackageConfig::boolean('search.aliases', true),
            categoryId: $category instanceof SkillCategory ? $category->id : $category,
            locale: $locale,
            includeInactive: $includeInactive,
            withAliases: $withAliases,
        ));
    }

    /**
     * Searches the language catalog by name, native name and codes.
     *
     * @param  bool|null  $partial  Null uses the configured default.
     * @return Builder<Language>
     */
    public function searchLanguages(string $term, ?bool $partial = null, bool $includeInactive = false): Builder
    {
        $term = Normalizer::normalize($term);

        return $this->container->make(LanguageSearchResolver::class)->search(new LanguageSearchCriteria(
            term: $term,
            partial: $this->usesPartialMatching($term, $partial),
            includeInactive: $includeInactive,
        ));
    }

    /**
     * Resolves a human-friendly term to the catalog skills it names: an exact
     * (normalized) canonical name, alias or slug, including inactive skills.
     *
     * Uses the registered skill search resolver in exact mode, so aliases are
     * resolved in one place only. An ambiguous alias returns every skill it
     * names; an unknown term returns an empty collection.
     *
     * @return EloquentCollection<int, Skill>
     */
    public function resolveSkills(string $term): EloquentCollection
    {
        $model = PackageConfig::model('skill', Skill::class);
        $skill = new $model;
        $slug = mb_strtolower(trim($term));

        if ($slug === '') {
            return $skill->newCollection();
        }

        $matches = $this->searchSkills($term, partial: false, includeInactive: true)
            ->reorder()
            ->select($skill->getQualifiedKeyName());

        return $model::query()
            ->where('slug', $slug)
            ->orWhereIn($skill->getKeyName(), $matches)
            ->orderBy($skill->getKeyName())
            ->get();
    }

    /**
     * Resolves a human-friendly term to the catalog languages it names: an
     * exact code, ISO 639 code, name or native name, including inactive
     * languages. An unknown term returns an empty collection.
     *
     * @return EloquentCollection<int, Language>
     */
    public function resolveLanguages(string $term): EloquentCollection
    {
        $query = $this->searchLanguages($term, partial: false, includeInactive: true)->reorder();

        return $query->orderBy($query->getModel()->getQualifiedKeyName())->get();
    }

    /**
     * @param  class-string<SkillSearchResolver>|SkillSearchResolver  $resolver
     */
    public function registerSkillSearchResolver(string|SkillSearchResolver $resolver): void
    {
        $this->bindResolver(SkillSearchResolver::class, $resolver);
    }

    /**
     * @param  class-string<LanguageSearchResolver>|LanguageSearchResolver  $resolver
     */
    public function registerLanguageSearchResolver(string|LanguageSearchResolver $resolver): void
    {
        $this->bindResolver(LanguageSearchResolver::class, $resolver);
    }

    public function skillProficiency(): ProficiencyScale
    {
        return ProficiencyScale::fromConfig(ProficiencyType::Skill);
    }

    public function languageProficiency(): ProficiencyScale
    {
        return ProficiencyScale::fromConfig(ProficiencyType::Language);
    }

    public function featureEnabled(Feature $feature): bool
    {
        return PackageConfig::featureEnabled($feature);
    }

    private function usesPartialMatching(string $term, ?bool $partial): bool
    {
        $partial ??= PackageConfig::boolean('search.partial_matching', true);

        return $partial && mb_strlen($term) >= PackageConfig::integer('search.min_partial_length', 2);
    }

    /**
     * @param  class-string  $contract
     * @param  class-string|object  $resolver
     */
    private function bindResolver(string $contract, string|object $resolver): void
    {
        if (is_object($resolver)) {
            $this->container->instance($contract, $resolver);

            return;
        }

        if (! is_a($resolver, $contract, true)) {
            throw new \InvalidArgumentException("[{$resolver}] must implement [{$contract}].");
        }

        $this->container->bind($contract, $resolver);
    }
}
