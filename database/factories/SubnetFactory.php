<?php

namespace Database\Factories;

use App\Models\Subnet;
use App\Models\VlanDomain;
use App\Support\Cidr;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subnet>
 */
class SubnetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cidr = Cidr::parse('10.'.fake()->numberBetween(0, 250).'.0.0/24');

        return [
            'vlan_domain_id' => VlanDomain::factory(),
            'cidr' => $cidr->label(),
            'network' => $cidr->network,
            'broadcast' => $cidr->broadcast(),
            'name' => fake()->word(),
        ];
    }
}
