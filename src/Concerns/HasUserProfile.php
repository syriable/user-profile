<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Event;
use Syriable\UserProfile\Enums\Feature;
use Syriable\UserProfile\Events\LanguageAdded;
use Syriable\UserProfile\Events\LanguageRemoved;
use Syriable\UserProfile\Events\LanguageUpdated;
use Syriable\UserProfile\Events\SkillAdded;
use Syriable\UserProfile\Events\SkillRemoved;
use Syriable\UserProfile\Events\SkillUpdated;
use Syriable\UserProfile\Exceptions\AmbiguousProfileEntry;
use Syriable\UserProfile\Exceptions\DuplicateProfileEntry;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Exceptions\ProfileEntryNotFound;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Award;
use Syriable\UserProfile\Models\Certification;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\ProfileLanguage;
use Syriable\UserProfile\Models\ProfileSkill;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Support\PackageConfig;
use Syriable\UserProfile\Support\ProfileCatalog;
use Syriable\UserProfile\Support\ProfileOwner;

/**
 * Makes any Eloquent model a profile owner: languages, skills, education,
 * certifications and awards, linked through polymorphic
 * `profileable_type` / `profileable_id` columns.
 *
 * Languages and skills accept a model, a primary key, or a human-friendly
 * term. For writes, a term must name exactly one catalog entry; see
 * UserProfile::resolveSkills() and UserProfile::resolveLanguages().
 *
 * @mixin Model
 */
trait HasUserProfile
{
    use FiltersProfileOwners;

    private const array PROFILE_LANGUAGE_ATTRIBUTES = ['proficiency_level', 'is_native', 'is_primary'];

    private const array PROFILE_SKILL_ATTRIBUTES = ['proficiency_level', 'years_of_experience', 'is_primary'];

    /**
     * Profile rows have no foreign key to their owner (a polymorphic column
     * can't reference several tables), so they are removed when the owner is
     * deleted. Soft-deleted owners keep their profile until force deleted.
     */
    public static function bootHasUserProfile(): void
    {
        static::deleted(function (Model $owner): void {
            if (method_exists($owner, 'isForceDeleting') && ! $owner->isForceDeleting()) {
                return;
            }

            if (method_exists($owner, 'deleteProfile')) {
                $owner->deleteProfile();
            }
        });
    }

    /**
     * @return MorphToMany<Language, $this, ProfileLanguage>
     */
    public function languages(): MorphToMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphToMany(PackageConfig::model('language', Language::class), 'profileable', PackageConfig::table('profile_languages'), 'profileable_id', 'language_id')
            ->using(PackageConfig::model('profile_language', ProfileLanguage::class))
            ->withPivot(self::PROFILE_LANGUAGE_ATTRIBUTES)
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<Skill, $this, ProfileSkill>
     */
    public function skills(): MorphToMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphToMany(PackageConfig::model('skill', Skill::class), 'profileable', PackageConfig::table('profile_skills'), 'profileable_id', 'skill_id')
            ->using(PackageConfig::model('profile_skill', ProfileSkill::class))
            ->withPivot(self::PROFILE_SKILL_ATTRIBUTES)
            ->withTimestamps();
    }

    /**
     * The owner's language pivot rows, without joining the catalog.
     *
     * @return MorphMany<ProfileLanguage, $this>
     */
    public function profileLanguages(): MorphMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphMany(PackageConfig::model('profile_language', ProfileLanguage::class), 'profileable');
    }

    /**
     * The owner's skill pivot rows, without joining the catalog.
     *
     * @return MorphMany<ProfileSkill, $this>
     */
    public function profileSkills(): MorphMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphMany(PackageConfig::model('profile_skill', ProfileSkill::class), 'profileable');
    }

    /**
     * @return MorphMany<Education, $this>
     */
    public function educations(): MorphMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphMany(PackageConfig::model('education', Education::class), 'profileable');
    }

    /**
     * @return MorphMany<Certification, $this>
     */
    public function certifications(): MorphMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphMany(PackageConfig::model('certification', Certification::class), 'profileable');
    }

    /**
     * @return MorphMany<Award, $this>
     */
    public function awards(): MorphMany
    {
        ProfileOwner::ensureCompatible($this);

        return $this->morphMany(PackageConfig::model('award', Award::class), 'profileable');
    }

    /**
     * Adds a language to the profile. Setting $isPrimary unsets the flag on
     * every other language of this owner.
     *
     * @throws DuplicateProfileEntry
     * @throws AmbiguousProfileEntry
     * @throws InvalidProficiency
     */
    public function addLanguage(
        Language|int|string $language,
        ?string $proficiency = null,
        bool $isNative = false,
        bool $isPrimary = false,
    ): ProfileLanguage {
        PackageConfig::ensureFeatureEnabled(Feature::Languages);

        $language = $this->resolveProfileLanguage($language);

        /** @var ProfileLanguage $pivot */
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
     * @throws AmbiguousProfileEntry
     * @throws InvalidProficiency
     */
    public function updateLanguage(Language|int|string $language, array $attributes): ProfileLanguage
    {
        PackageConfig::ensureFeatureEnabled(Feature::Languages);

        $language = $this->resolveProfileLanguage($language);

        /** @var ProfileLanguage $pivot */
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

    /**
     * Whether the owner has the language. A term naming several languages
     * matches when the owner has any of them.
     */
    public function hasLanguage(Language|int|string $language): bool
    {
        return $this->profileLanguages()
            ->whereIn('language_id', $this->profileLanguageIds([$language]))
            ->exists();
    }

    /**
     * Adds a skill to the profile. Setting $isPrimary unsets the flag on
     * every other skill of this owner.
     *
     * @throws DuplicateProfileEntry
     * @throws AmbiguousProfileEntry
     * @throws InvalidProficiency
     */
    public function addSkill(
        Skill|int|string $skill,
        ?string $proficiency = null,
        ?int $yearsOfExperience = null,
        bool $isPrimary = false,
    ): ProfileSkill {
        PackageConfig::ensureFeatureEnabled(Feature::Skills);

        $skill = $this->resolveProfileSkill($skill);

        /** @var ProfileSkill $pivot */
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
     * @throws AmbiguousProfileEntry
     * @throws InvalidProficiency
     */
    public function updateSkill(Skill|int|string $skill, array $attributes): ProfileSkill
    {
        PackageConfig::ensureFeatureEnabled(Feature::Skills);

        $skill = $this->resolveProfileSkill($skill);

        /** @var ProfileSkill $pivot */
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

    /**
     * Whether the owner has the skill. A term naming several skills (an
     * ambiguous alias) matches when the owner has any of them.
     */
    public function hasSkill(Skill|int|string $skill): bool
    {
        return $this->profileSkills()
            ->whereIn('skill_id', $this->profileSkillIds([$skill]))
            ->exists();
    }

    /**
     * Deletes all of this owner's profile data: pivot rows, education,
     * certifications and awards. Catalog entries are untouched.
     *
     * Runs automatically when the owner model is deleted (or force deleted,
     * for soft-deleting owners). Call it yourself before deleting owners with
     * a query builder delete, which fires no model events.
     */
    public function deleteProfile(): void
    {
        $this->getConnection()->transaction(function (): void {
            if (PackageConfig::featureEnabled(Feature::Languages)) {
                $this->profileLanguages()->toBase()->delete();
            }

            if (PackageConfig::featureEnabled(Feature::Skills)) {
                $this->profileSkills()->toBase()->delete();
            }

            if (PackageConfig::featureEnabled(Feature::Education)) {
                $this->educations()->toBase()->delete();
            }

            if (PackageConfig::featureEnabled(Feature::Certifications)) {
                $this->certifications()->toBase()->delete();
            }

            if (PackageConfig::featureEnabled(Feature::Awards)) {
                $this->awards()->toBase()->delete();
            }
        });
    }

    /**
     * Resolves a language for a write, which must name exactly one entry.
     *
     * @throws ProfileEntryNotFound
     * @throws AmbiguousProfileEntry
     */
    protected function resolveProfileLanguage(Language|int|string $language): Language
    {
        if ($language instanceof Language) {
            return $language;
        }

        $model = PackageConfig::model('language', Language::class);

        if (is_int($language)) {
            return $model::query()->find($language) ?? throw ProfileEntryNotFound::inCatalog($model, $language);
        }

        $candidates = UserProfile::resolveLanguages($language);

        return match ($candidates->count()) {
            0 => throw ProfileEntryNotFound::inCatalog($model, $language),
            1 => $candidates->firstOrFail(),
            default => throw new AmbiguousProfileEntry($language, $candidates),
        };
    }

    /**
     * Resolves a skill for a write, which must name exactly one entry.
     *
     * @throws ProfileEntryNotFound
     * @throws AmbiguousProfileEntry
     */
    protected function resolveProfileSkill(Skill|int|string $skill): Skill
    {
        if ($skill instanceof Skill) {
            return $skill;
        }

        $model = PackageConfig::model('skill', Skill::class);

        if (is_int($skill)) {
            return $model::query()->find($skill) ?? throw ProfileEntryNotFound::inCatalog($model, $skill);
        }

        $candidates = UserProfile::resolveSkills($skill);

        return match ($candidates->count()) {
            0 => throw ProfileEntryNotFound::inCatalog($model, $skill),
            1 => $candidates->firstOrFail(),
            default => throw new AmbiguousProfileEntry($skill, $candidates),
        };
    }
}
