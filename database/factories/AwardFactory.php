<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\UserProfile\Models\Award;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
    {
        return [
            'title' => 'Excellence Award',
            'issuer' => $this->faker->company(),
            'date_received' => $this->faker->dateTimeBetween('-5 years'),
        ];
    }
}
