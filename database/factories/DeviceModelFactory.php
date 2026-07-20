<?php

namespace Database\Factories;

use App\Models\DeviceModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceModel>
 */
class DeviceModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor' => fake()->company(),
            'model' => fake()->unique()->bothify('SW-####'),
            'kind' => 'switch',
            'u_height' => 1,
        ];
    }

    /**
     * A model whose devices get a single run of copper ports.
     */
    public function withPorts(int $count = 24): static
    {
        return $this->afterCreating(fn (DeviceModel $model) => $model->portTemplates()->create([
            'start_number' => 1,
            'count' => $count,
            'media' => 'rj45',
            'speed_mbps' => 1000,
            'role' => 'network',
        ]));
    }

    public function patchPanel(int $count = 24): static
    {
        return $this->state(fn () => ['kind' => 'patch_panel'])
            ->afterCreating(function (DeviceModel $model) use ($count) {
                foreach (['front', 'rear'] as $sort => $role) {
                    $model->portTemplates()->create([
                        'start_number' => 1,
                        'count' => $count,
                        'media' => 'rj45',
                        'role' => $role,
                        'sort' => $sort,
                    ]);
                }
            });
    }
}
