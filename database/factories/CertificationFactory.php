<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\UserProfile\Models\Certification;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        return [
            'name' => 'Professional Certificate',
            'issuing_organization' => $this->faker->company(),
            'issue_date' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
            'expiration_date' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'issue_date' => now()->subYears(3),
            'expiration_date' => now()->subYear(),
        ]);
    }
}
