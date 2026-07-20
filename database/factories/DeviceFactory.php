<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Rack;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_model_id' => DeviceModel::factory()->withPorts(),
            'site_id' => Site::factory(),
            'name' => fake()->unique()->bothify('SW-##'),
            'face' => 'front',
            'status' => 'active',
        ];
    }

    /**
     * Mount the device in a rack, in the same site as the rack itself.
     */
    public function mountedIn(Rack $rack, int $position = 1): static
    {
        return $this->state(fn () => [
            'rack_id' => $rack->id,
            'position_u' => $position,
            'site_id' => $rack->room->site_id,
        ]);
    }
}
