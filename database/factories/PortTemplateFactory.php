<?php

namespace Database\Factories;

use App\Models\DeviceModel;
use App\Models\PortTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortTemplate>
 */
class PortTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_model_id' => DeviceModel::factory(),
            'name_prefix' => '',
            'start_number' => 1,
            'count' => 24,
            'media' => 'rj45',
            'speed_mbps' => 1000,
            'role' => 'network',
            'sort' => 0,
        ];
    }
}
