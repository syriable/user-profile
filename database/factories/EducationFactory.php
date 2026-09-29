<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\UserProfile\Models\Education;

/**
 * @extends Factory<Education>
 */
class EducationFactory extends Factory
{
    protected $model = Education::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-15 years', '-5 years');

        return [
            'institution_name' => $this->faker->company().' University',
            'degree' => 'Bachelor',
            'field_of_study' => 'Computer Science',
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+4 years'),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(['end_date' => null, 'graduation_year' => null, 'is_current' => true]);
    }
}
