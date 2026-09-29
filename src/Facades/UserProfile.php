<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Facades;

use Illuminate\Support\Facades\Facade;
use Syriable\UserProfile\UserProfileManager;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<\Syriable\UserProfile\Models\Skill> searchSkills(string $term, \Syriable\UserProfile\Models\SkillCategory|int|null $category = null, ?bool $partial = null, ?string $locale = null, bool $includeInactive = false, bool $withAliases = false)
 * @method static \Illuminate\Database\Eloquent\Builder<\Syriable\UserProfile\Models\Language> searchLanguages(string $term, ?bool $partial = null, bool $includeInactive = false)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \Syriable\UserProfile\Models\Skill> resolveSkills(string $term)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \Syriable\UserProfile\Models\Language> resolveLanguages(string $term)
 * @method static void registerSkillSearchResolver(class-string<\Syriable\UserProfile\Contracts\SkillSearchResolver>|\Syriable\UserProfile\Contracts\SkillSearchResolver $resolver)
 * @method static void registerLanguageSearchResolver(class-string<\Syriable\UserProfile\Contracts\LanguageSearchResolver>|\Syriable\UserProfile\Contracts\LanguageSearchResolver $resolver)
 * @method static \Syriable\UserProfile\Support\ProficiencyScale skillProficiency()
 * @method static \Syriable\UserProfile\Support\ProficiencyScale languageProficiency()
 * @method static bool featureEnabled(\Syriable\UserProfile\Enums\Feature $feature)
 *
 * @see UserProfileManager
 */
final class UserProfile extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UserProfileManager::class;
    }
}
