<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * A profile owner's relationship with a skill: proficiency and experience.
 *
 * Rows are written through the HasUserProfile helpers. When read through
 * Skill::profileSkills() they are plain records for reporting.
 *
 * @property string $profileable_type
 * @property int|string $profileable_id
 * @property int $skill_id
 * @property string|null $proficiency_level
 * @property int|null $years_of_experience
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProfileSkill extends MorphPivot
{
    public $incrementing = false;

    public function getTable(): string
    {
        return PackageConfig::table('profile_skills');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function profileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(PackageConfig::model('skill', Skill::class), 'skill_id');
    }

    public function proficiencyLabel(): ?string
    {
        return $this->proficiency_level === null
            ? null
            : UserProfile::skillProficiency()->label($this->proficiency_level);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skill_id' => 'integer',
            'years_of_experience' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
