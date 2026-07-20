<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\VlanDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vlan_domain_id' => VlanDomain::factory(),
            'name' => fake()->unique()->city(),
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'kind' => 'complex',
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'color' => fake()->hexColor(),
        ];
    }

    /**
     * Put this site in the same switched network as another one.
     */
    public function inDomainOf(Site $site): static
    {
        return $this->state(fn () => ['vlan_domain_id' => $site->vlan_domain_id]);
    }
}
