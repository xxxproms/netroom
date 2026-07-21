<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workplace_id' => Workplace::factory(),
            'label' => fake()->unique()->bothify('##-#'),
            'media' => 'rj45',
        ];
    }
}
