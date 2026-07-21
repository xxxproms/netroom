<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Tunnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tunnel>
 */
class TunnelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_a_id' => Site::factory(),
            'site_b_id' => Site::factory(),
            'type' => 'kerio_vpn',
            'status' => 'up',
        ];
    }
}
