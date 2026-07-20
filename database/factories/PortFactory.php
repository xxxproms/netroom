<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Port>
 */
class PortFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 48);

        return [
            'device_id' => Device::factory(),
            'name' => (string) $number,
            'number' => $number,
            'media' => 'rj45',
            'speed_mbps' => 1000,
            'role' => 'network',
        ];
    }
}
