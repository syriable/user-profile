<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\UserProfile\Models\SkillCategory;

/**
 * @extends Factory<SkillCategory>
 */
class SkillCategoryFactory extends Factory
{
    protected $model = SkillCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->bothify('Category ###??'),
            'description' => null,
        ];
    }
}
