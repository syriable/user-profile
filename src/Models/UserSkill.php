<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * A user's relationship with a skill: proficiency and experience.
 *
 * @property int|string $user_id
 * @property int $skill_id
 * @property string|null $proficiency_level
 * @property int|null $years_of_experience
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserSkill extends Pivot
{
    public $incrementing = false;

    public function getTable(): string
    {
        return PackageConfig::table('user_skills');
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
