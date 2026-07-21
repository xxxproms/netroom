<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workplace>
 */
class WorkplaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->unique()->bothify('Desk ##'),
            'person' => fake()->name(),
            'floor' => (string) fake()->numberBetween(1, 5),
        ];
    }
}
