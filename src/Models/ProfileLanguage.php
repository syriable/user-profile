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
 * A profile owner's relationship with a language: proficiency and the
 * native/primary flags.
 *
 * Rows are written through the HasUserProfile helpers. When read through
 * Language::profileLanguages() they are plain records for reporting.
 *
 * @property string $profileable_type
 * @property int|string $profileable_id
 * @property int $language_id
 * @property string|null $proficiency_level
 * @property bool $is_native
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProfileLanguage extends MorphPivot
{
    public $incrementing = false;

    public function getTable(): string
    {
        return PackageConfig::table('profile_languages');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function profileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(PackageConfig::model('language', Language::class), 'language_id');
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
