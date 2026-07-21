<?php

namespace Database\Factories;

use App\Models\Cable;
use App\Models\Concerns\Terminates;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Cable>
 */
class CableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'media' => 'utp',
            'status' => 'connected',
            'label' => fake()->unique()->bothify('L-###'),
        ];
    }

    /**
     * Plugs the cable into the two ends given, taking the site from the first.
     */
    public function between(Model&Terminates $a, Model&Terminates $b): self
    {
        return $this->state(fn () => [
            'a_type' => $a->getMorphClass(),
            'a_id' => $a->getKey(),
            'b_type' => $b->getMorphClass(),
            'b_id' => $b->getKey(),
        ]);
    }

    public function fibre(int $strands = 2): self
    {
        return $this->state(fn () => ['media' => 'fibre', 'strands' => $strands]);
    }
}
