<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\UserProfile\Models\Skill;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->bothify('Skill ####??'),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * @param  list<string>  $aliases
     */
    public function withAliases(array $aliases, ?string $locale = null): static
    {
        return $this->afterCreating(function (Skill $skill) use ($aliases, $locale): void {
            foreach ($aliases as $alias) {
                $skill->addAlias($alias, $locale);
            }
        });
    }
}
