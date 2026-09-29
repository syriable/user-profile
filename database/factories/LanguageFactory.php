<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\UserProfile\Models\Language;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        $code = $this->faker->unique()->lexify('???');

        return [
            'name' => 'Language '.strtoupper($code),
            'native_name' => null,
            'code' => $code,
            'iso_639_1' => null,
            'iso_639_3' => $code,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
