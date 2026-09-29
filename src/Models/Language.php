<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Database\Factories\LanguageFactory;
use Syriable\UserProfile\Support\LanguageTag;
use Syriable\UserProfile\Support\Normalizer;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * A reusable language catalog entry, independent of any user's proficiency.
 *
 * @property int $id
 * @property string $name
 * @property string|null $native_name
 * @property string $code BCP 47 tag used as the canonical identifier.
 * @property string|null $iso_639_1
 * @property string|null $iso_639_3
 * @property bool $is_active
 * @property string $normalized_name
 * @property string|null $normalized_native_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    use ValidatesAttributes;

    protected $fillable = [
        'name',
        'native_name',
        'code',
        'iso_639_1',
        'iso_639_3',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function getTable(): string
    {
        return PackageConfig::table('languages');
    }

    /**
     * @return BelongsToMany<Model, $this, UserLanguage>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(PackageConfig::userModel(), PackageConfig::table('user_languages'), 'language_id', 'user_id')
            ->using(PackageConfig::model('user_language', UserLanguage::class))
            ->withPivot(['proficiency_level', 'is_native', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => ['required', 'string', 'max:255', $this->uniqueRule('normalized_name')],
            'native_name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:35', 'regex:'.LanguageTag::BCP47, $this->uniqueRule('code')],
            'iso_639_1' => ['nullable', 'string', 'regex:'.LanguageTag::ISO_639_1, $this->uniqueRule('iso_639_1')],
            'iso_639_3' => ['nullable', 'string', 'regex:'.LanguageTag::ISO_639_3, $this->uniqueRule('iso_639_3')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn (string $value): array => [
            'name' => trim($value),
            'normalized_name' => Normalizer::normalize($value),
        ]);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function nativeName(): Attribute
    {
        return Attribute::make(set: fn (?string $value): array => [
            'native_name' => $value === null ? null : trim($value),
            'normalized_native_name' => $value === null ? null : Normalizer::normalize($value),
        ]);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => LanguageTag::canonicalize($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function iso6391(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : strtolower(trim($value)));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function iso6393(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : strtolower(trim($value)));
    }

    protected static function newFactory(): LanguageFactory
    {
        return LanguageFactory::new();
    }
}
