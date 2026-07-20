<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->unique()->bothify('Server room ##'),
            'floor' => (string) fake()->numberBetween(1, 5),
            'kind' => 'server_room',
        ];
    }
}
