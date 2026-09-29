<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Database\Factories\SkillFactory;
use Syriable\UserProfile\Support\Normalizer;
use Syriable\UserProfile\Support\PackageConfig;
use Syriable\UserProfile\Support\SlugGenerator;

/**
 * A canonical skill in the reusable catalog. Alternative names live in
 * skill_aliases and always point back to one canonical skill.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $normalized_name
 * @property string|null $description
 * @property int|null $category_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SkillAlias> $aliases
 * @property-read SkillCategory|null $category
 */
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    use ValidatesAttributes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function getTable(): string
    {
        return PackageConfig::table('skills');
    }

    /**
     * @return HasMany<SkillAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(PackageConfig::model('skill_alias', SkillAlias::class), 'skill_id');
    }

    /**
     * @return BelongsTo<SkillCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PackageConfig::model('skill_category', SkillCategory::class), 'category_id');
    }

    /**
     * @return BelongsToMany<Model, $this, UserSkill>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(PackageConfig::userModel(), PackageConfig::table('user_skills'), 'skill_id', 'user_id')
            ->using(PackageConfig::model('user_skill', UserSkill::class))
            ->withPivot(['proficiency_level', 'years_of_experience', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Adds an alternative name. Adding an alias that already exists for this
     * skill and locale returns the existing alias.
     */
    public function addAlias(string $alias, ?string $locale = null): SkillAlias
    {
        /** @var SkillAlias */
        return $this->aliases()->firstOrCreate(
            ['normalized_alias' => Normalizer::normalize($alias), 'locale' => $locale],
            ['alias' => $alias],
        );
    }

    /**
     * Removes an alias (for the given locale, or every locale when null).
     * Returns the number of aliases removed.
     */
    public function removeAlias(string $alias, ?string $locale = null): int
    {
        $aliases = $this->aliases()->where('normalized_alias', Normalizer::normalize($alias));

        if ($locale !== null) {
            $aliases->where('locale', $locale);
        }

        return $aliases->toBase()->delete();
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
            'slug' => ['required', 'string', 'max:255', $this->uniqueRule('slug')],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
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
            'category_id' => 'integer',
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

    protected function prepareAttributes(): void
    {
        if (blank($this->getAttribute('slug')) && is_string($this->getAttribute('name'))) {
            $this->setAttribute('slug', SlugGenerator::unique($this, $this->name));
        }
    }

    protected static function newFactory(): SkillFactory
    {
        return SkillFactory::new();
    }
}
