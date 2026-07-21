<?php

namespace Database\Factories;

use App\Models\IpAddress;
use App\Models\Subnet;
use App\Support\Cidr;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpAddress>
 */
class IpAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = '10.40.0.'.fake()->unique()->numberBetween(2, 250);

        return [
            'subnet_id' => Subnet::factory(),
            'address' => Cidr::toLong($address),
            'address_text' => $address,
            'status' => 'reserved',
        ];
    }
}
