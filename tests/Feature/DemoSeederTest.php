<?php

use App\Models\Device;
use App\Models\Port;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('the demo estate seeds into a coherent network', function () {
    $this->seed(DemoSeeder::class);

    $campus = Site::whereIn('code', ['NORTH', 'SOUTH'])->get();

    expect(Site::count())->toBe(5)
        // The neighbouring complexes share one VLAN plan.
        ->and($campus->pluck('vlan_domain_id')->unique())->toHaveCount(1)
        ->and(Device::count())->toBeGreaterThan(20)
        ->and(Port::whereNotNull('description')->count())->toBeGreaterThan(100)
        // A cottage keeps its router on a shelf rather than in a rack.
        ->and(Device::whereNull('rack_id')->count())->toBe(1);
});

test('the demo accounts land on the access levels they advertise', function () {
    $this->seed(DemoSeeder::class);

    $technician = User::firstWhere('email', 'tech@example.com');

    expect($technician->hasRole('technician'))->toBeTrue()
        ->and($technician->has_all_sites)->toBeFalse()
        ->and($technician->sites->pluck('code')->all())->toBe(['NORTH', 'SOUTH'])
        ->and($technician->canAccessSite(Site::firstWhere('code', 'CITY')))->toBeFalse();
});

test('seeding twice does not duplicate the estate', function () {
    $this->seed(DemoSeeder::class);
    $devices = Device::count();

    $this->seed(DemoSeeder::class);

    expect(Device::count())->toBe($devices)
        ->and(Site::count())->toBe(5);
});
