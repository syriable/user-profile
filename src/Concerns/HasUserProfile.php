<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Event;
use Syriable\UserProfile\Enums\Feature;
use Syriable\UserProfile\Events\LanguageAdded;
use Syriable\UserProfile\Events\LanguageRemoved;
use Syriable\UserProfile\Events\LanguageUpdated;
use Syriable\UserProfile\Events\SkillAdded;
use Syriable\UserProfile\Events\SkillRemoved;
use Syriable\UserProfile\Events\SkillUpdated;
use Syriable\UserProfile\Exceptions\DuplicateProfileEntry;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Exceptions\ProfileEntryNotFound;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Award;
use Syriable\UserProfile\Models\Certification;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\UserLanguage;
use Syriable\UserProfile\Models\UserSkill;
use Syriable\UserProfile\Support\LanguageTag;
use Syriable\UserProfile\Support\PackageConfig;
use Syriable\UserProfile\Support\ProfileCatalog;

/**
 * Adds profile relationships and helpers to an application's user model.
 *
 * Languages and skills accept a model instance, a primary key, or a string
 * identifier (the language `code` or the skill `slug`).
 *
 * @mixin Model
 */
trait HasUserProfile
{
    private const array PROFILE_LANGUAGE_ATTRIBUTES = ['proficiency_level', 'is_native', 'is_primary'];

    private const array PROFILE_SKILL_ATTRIBUTES = ['proficiency_level', 'years_of_experience', 'is_primary'];

    /**
     * @return BelongsToMany<Language, $this, UserLanguage>
     */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(PackageConfig::model('language', Language::class), PackageConfig::table('user_languages'), 'user_id', 'language_id')
            ->using(PackageConfig::model('user_language', UserLanguage::class))
            ->withPivot(['proficiency_level', 'is_native', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Skill, $this, UserSkill>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(PackageConfig::model('skill', Skill::class), PackageConfig::table('user_skills'), 'user_id', 'skill_id')
            ->using(PackageConfig::model('user_skill', UserSkill::class))
            ->withPivot(['proficiency_level', 'years_of_experience', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Education, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(PackageConfig::model('education', Education::class), 'user_id');
    }

    /**
     * @return HasMany<Certification, $this>
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(PackageConfig::model('certification', Certification::class), 'user_id');
    }

    /**
     * @return HasMany<Award, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(PackageConfig::model('award', Award::class), 'user_id');
    }

    /**
     * Adds a language to the profile. Setting $isPrimary unsets the flag on
     * every other language of this user.
     *
     * @throws DuplicateProfileEntry
     * @throws InvalidProficiency
     */
    public function addLanguage(
        Language|int|string $language,
        ?string $proficiency = null,
        bool $isNative = false,
        bool $isPrimary = false,
    ): UserLanguage {
        PackageConfig::ensureFeatureEnabled(Feature::Languages);

        $language = $this->resolveProfileLanguage($language);

        /** @var UserLanguage $pivot */
        $pivot = ProfileCatalog::attach($this->languages(), $language, ProfileCatalog::attributes(
            ['proficiency_level' => $proficiency, 'is_native' => $isNative, 'is_primary' => $isPrimary],
            self::PROFILE_LANGUAGE_ATTRIBUTES,
            UserProfile::languageProficiency(),
        ));

        Event::dispatch(new LanguageAdded($this, $language, $pivot));

        return $pivot;
    }

    /**
     * Updates pivot attributes of a language already on the profile.
     *
     * @param  array{proficiency_level?: string|null, is_native?: bool, is_primary?: bool}  $attributes
     *
     * @throws ProfileEntryNotFound
     * @throws InvalidProficiency
     */
    public function updateLanguage(Language|int|string $language, array $attributes): UserLanguage
    {
        PackageConfig::ensureFeatureEnabled(Feature::Languages);

        $language = $this->resolveProfileLanguage($language);

        /** @var UserLanguage $pivot */
        $pivot = ProfileCatalog::update($this->languages(), $language, ProfileCatalog::attributes(
            $attributes,
            self::PROFILE_LANGUAGE_ATTRIBUTES,
            UserProfile::languageProficiency(),
        ));

        Event::dispatch(new LanguageUpdated($this, $language, $pivot));

        return $pivot;
    }

    /**
     * Removes a language from the profile. The catalog language is untouched.
     */
    public function removeLanguage(Language|int|string $language): bool
    {
        PackageConfig::ensureFeatureEnabled(Feature::Languages);

        $language = $this->resolveProfileLanguage($language);

        $removed = ProfileCatalog::detach($this->languages(), $language);

        if ($removed) {
            Event::dispatch(new LanguageRemoved($this, $language));
        }

        return $removed;
    }

    public function hasLanguage(Language|int|string $language): bool
    {
        $language = $this->resolveProfileLanguage($language, orFail: false);

        return $language !== null && ProfileCatalog::has($this->languages(), $language);
    }

    /**
     * Adds a skill to the profile. Setting $isPrimary unsets the flag on
     * every other skill of this user.
     *
     * @throws DuplicateProfileEntry
     * @throws InvalidProficiency
     */
    public function addSkill(
        Skill|int|string $skill,
        ?string $proficiency = null,
        ?int $yearsOfExperience = null,
        bool $isPrimary = false,
    ): UserSkill {
        PackageConfig::ensureFeatureEnabled(Feature::Skills);

        $skill = $this->resolveProfileSkill($skill);

        /** @var UserSkill $pivot */
        $pivot = ProfileCatalog::attach($this->skills(), $skill, ProfileCatalog::attributes(
            ['proficiency_level' => $proficiency, 'years_of_experience' => $yearsOfExperience, 'is_primary' => $isPrimary],
            self::PROFILE_SKILL_ATTRIBUTES,
            UserProfile::skillProficiency(),
        ));

        Event::dispatch(new SkillAdded($this, $skill, $pivot));

        return $pivot;
    }

    /**
     * Updates pivot attributes of a skill already on the profile.
     *
     * @param  array{proficiency_level?: string|null, years_of_experience?: int|null, is_primary?: bool}  $attributes
     *
     * @throws ProfileEntryNotFound
     * @throws InvalidProficiency
     */
    public function updateSkill(Skill|int|string $skill, array $attributes): UserSkill
    {
        PackageConfig::ensureFeatureEnabled(Feature::Skills);

        $skill = $this->resolveProfileSkill($skill);

        /** @var UserSkill $pivot */
        $pivot = ProfileCatalog::update($this->skills(), $skill, ProfileCatalog::attributes(
            $attributes,
            self::PROFILE_SKILL_ATTRIBUTES,
            UserProfile::skillProficiency(),
        ));

        Event::dispatch(new SkillUpdated($this, $skill, $pivot));

        return $pivot;
    }

    /**
     * Removes a skill from the profile. The catalog skill is untouched.
     */
    public function removeSkill(Skill|int|string $skill): bool
    {
        PackageConfig::ensureFeatureEnabled(Feature::Skills);

        $skill = $this->resolveProfileSkill($skill);

        $removed = ProfileCatalog::detach($this->skills(), $skill);

        if ($removed) {
            Event::dispatch(new SkillRemoved($this, $skill));
        }

        return $removed;
    }

    public function hasSkill(Skill|int|string $skill): bool
    {
        $skill = $this->resolveProfileSkill($skill, orFail: false);

        return $skill !== null && ProfileCatalog::has($this->skills(), $skill);
    }

    /**
     * Users who have the skill, optionally at or above a proficiency level.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereHasSkill(Builder $query, Skill|int|string $skill, ?string $minimumProficiency = null): void
    {
        $skill = $this->resolveProfileSkill($skill);
        $pivotTable = PackageConfig::table('user_skills');

        $query->whereHas('skills', fn (Builder $skills) => $skills
            ->whereKey($skill->getKey())
            ->when($minimumProficiency !== null, fn (Builder $skills) => $skills->whereIn(
                "{$pivotTable}.proficiency_level",
                UserProfile::skillProficiency()->atLeast((string) $minimumProficiency),
            )));
    }

    /**
     * Users who speak the language, optionally at or above a proficiency
     * level. Native speakers always match a proficiency constraint.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereHasLanguage(Builder $query, Language|int|string $language, ?string $minimumProficiency = null): void
    {
        $language = $this->resolveProfileLanguage($language);
        $pivotTable = PackageConfig::table('user_languages');

        $query->whereHas('languages', fn (Builder $languages) => $languages
            ->whereKey($language->getKey())
            ->when($minimumProficiency !== null, fn (Builder $languages) => $languages->getQuery()->where(
                fn (QueryBuilder $level) => $level
                    ->where("{$pivotTable}.is_native", true)
                    ->orWhereIn(
                        "{$pivotTable}.proficiency_level",
                        UserProfile::languageProficiency()->atLeast((string) $minimumProficiency),
                    ),
            )));
    }

    /**
     * @return ($orFail is true ? Language : Language|null)
     */
    protected function resolveProfileLanguage(Language|int|string $language, bool $orFail = true): ?Language
    {
        $model = PackageConfig::model('language', Language::class);

        if ($language instanceof Language) {
            return $language;
        }

        $resolved = is_int($language)
            ? $model::query()->find($language)
            : $model::query()->where('code', LanguageTag::canonicalize($language))->first();

        if ($resolved === null && $orFail) {
            throw ProfileEntryNotFound::inCatalog($model, $language);
        }

        return $resolved;
    }

    /**
     * @return ($orFail is true ? Skill : Skill|null)
     */
    protected function resolveProfileSkill(Skill|int|string $skill, bool $orFail = true): ?Skill
    {
        $model = PackageConfig::model('skill', Skill::class);

        if ($skill instanceof Skill) {
            return $skill;
        }

        $resolved = is_int($skill)
            ? $model::query()->find($skill)
            : $model::query()->where('slug', $skill)->first();

        if ($resolved === null && $orFail) {
            throw ProfileEntryNotFound::inCatalog($model, $skill);
        }

        return $resolved;
    }
}
