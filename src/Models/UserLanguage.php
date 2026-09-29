<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * A user's relationship with a language: proficiency and native/primary flags.
 *
 * @property int|string $user_id
 * @property int $language_id
 * @property string|null $proficiency_level
 * @property bool $is_native
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserLanguage extends Pivot
{
    public $incrementing = false;

    public function getTable(): string
    {
        return PackageConfig::table('user_languages');
    }

    public function proficiencyLabel(): ?string
    {
        return $this->proficiency_level === null
            ? null
            : UserProfile::languageProficiency()->label($this->proficiency_level);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language_id' => 'integer',
            'is_native' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }
}
