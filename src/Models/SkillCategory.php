<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Database\Factories\SkillCategoryFactory;
use Syriable\UserProfile\Support\PackageConfig;
use Syriable\UserProfile\Support\SlugGenerator;

/**
 * Optional grouping for skills ("Programming Languages", "Design", ...).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SkillCategory extends Model
{
    /** @use HasFactory<SkillCategoryFactory> */
    use HasFactory;

    use ValidatesAttributes;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function getTable(): string
    {
        return PackageConfig::table('skill_categories');
    }

    /**
     * @return HasMany<Skill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(PackageConfig::model('skill', Skill::class), 'category_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', $this->uniqueRule('slug')],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function prepareAttributes(): void
    {
        if (blank($this->getAttribute('slug')) && is_string($this->getAttribute('name'))) {
            $this->setAttribute('slug', SlugGenerator::unique($this, $this->name));
        }
    }

    protected static function newFactory(): SkillCategoryFactory
    {
        return SkillCategoryFactory::new();
    }
}
