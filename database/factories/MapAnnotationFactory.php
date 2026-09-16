<?php

namespace Database\Factories;

use App\Models\MapAnnotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MapAnnotation>
 */
class MapAnnotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => null,
            'type' => 'zone',
            'text' => fake()->words(2, true),
            'map_x' => 0,
            'map_y' => 0,
            'width' => 320,
            'height' => 220,
            'color' => null,
            'z' => 0,
        ];
    }

    public function note(): self
    {
        return $this->state(fn () => [
            'type' => 'note',
            'width' => 220,
            'height' => 120,
            'z' => 1,
        ]);
    }
}
