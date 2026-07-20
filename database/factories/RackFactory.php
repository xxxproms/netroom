<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => fake()->unique()->bothify('Rack-##'),
            'u_height' => 42,
            'kind' => 'rack',
        ];
    }
}
