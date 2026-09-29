<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Support\LanguageTag;
use Syriable\UserProfile\Support\Normalizer;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * An alternative name, abbreviation or spelling of a canonical skill.
 *
 * Aliases are intentionally not globally unique: "JS" may legitimately point
 * to more than one skill. They are unique per skill and locale.
 *
 * @property int $id
 * @property int $skill_id
 * @property string $alias
 * @property string $normalized_alias
 * @property string|null $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Skill $skill
 */
class SkillAlias extends Model
{
    use ValidatesAttributes;

    protected $fillable = [
        'alias',
        'locale',
    ];

    public function getTable(): string
    {
        return PackageConfig::table('skill_aliases');
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(PackageConfig::model('skill', Skill::class), 'skill_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skill_id' => ['required'],
            'alias' => ['required', 'string', 'max:255'],
            'normalized_alias' => [
                'required',
                'string',
                'max:255',
                $this->uniqueRule('normalized_alias')
                    ->where('skill_id', $this->skill_id)
                    ->where('locale', $this->locale),
            ],
            'locale' => ['nullable', 'string', 'max:35', 'regex:'.LanguageTag::BCP47],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skill_id' => 'integer',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function alias(): Attribute
    {
        return Attribute::make(set: fn (string $value): array => [
            'alias' => trim($value),
            'normalized_alias' => Normalizer::normalize($value),
        ]);
    }
}
