<?php

namespace Database\Factories;

use App\Models\VlanDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VlanDomain>
 */
class VlanDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'notes' => null,
        ];
    }
}
