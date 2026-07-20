<?php

namespace Database\Factories;

use App\Models\Vlan;
use App\Models\VlanDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vlan>
 */
class VlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vlan_domain_id' => VlanDomain::factory(),
            'vid' => fake()->unique()->numberBetween(2, 4094),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
