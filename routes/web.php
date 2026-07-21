<?php

use App\Http\Controllers\CableController;
use App\Http\Controllers\CableTargetController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceModelController;
use App\Http\Controllers\NetworkMapController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RackElevationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SiteContextController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TraceController;
use App\Http\Controllers\TunnelController;
use App\Http\Controllers\VlanController;
use App\Http\Controllers\VlanDomainController;
use App\Http\Controllers\VlanMatrixController;
use App\Http\Controllers\WorkplaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // The site picker in the header.
    Route::put('context/site', [SiteContextController::class, 'update'])->name('context.site');

    Route::resource('sites', SiteController::class)->except(['create', 'edit']);
    Route::resource('rooms', RoomController::class)->except(['create', 'edit']);
    Route::resource('racks', RackController::class)->except(['create', 'edit', 'show']);

    Route::get('racks/{rack}', [RackElevationController::class, 'show'])->name('racks.show');
    Route::put('racks/{rack}/devices/{device}/position', [RackElevationController::class, 'move'])
        ->name('racks.devices.move');

    Route::resource('devices', DeviceController::class)->except(['create', 'edit']);
    Route::patch('ports/{port}', [PortController::class, 'update'])->name('ports.update');

    Route::get('devices/{device}/vlans', [VlanMatrixController::class, 'show'])->name('devices.vlans');
    Route::put('devices/{device}/vlans', [VlanMatrixController::class, 'update'])->name('devices.vlans.update');
    Route::resource('device-models', DeviceModelController::class)->except(['create', 'edit', 'show']);
    Route::resource('vlan-domains', VlanDomainController::class)->except(['create', 'edit', 'show']);

    Route::resource('workplaces', WorkplaceController::class)->except(['create', 'edit']);
    Route::post('workplaces/{workplace}/outlets', [OutletController::class, 'store'])->name('outlets.store');
    Route::patch('outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
    Route::delete('outlets/{outlet}', [OutletController::class, 'destroy'])->name('outlets.destroy');

    Route::get('cables/targets', [CableTargetController::class, 'index'])->name('cables.targets');
    Route::resource('cables', CableController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('ports/{port}/trace', [TraceController::class, 'port'])->name('ports.trace');
    Route::get('outlets/{outlet}/trace', [TraceController::class, 'outlet'])->name('outlets.trace');

    Route::get('map', [NetworkMapController::class, 'index'])->name('map');
    Route::get('map/sites/{site}', [NetworkMapController::class, 'site'])->name('map.site');
    Route::patch('map/sites/{site}/position', [NetworkMapController::class, 'moveSite'])->name('map.sites.move');
    Route::patch('map/devices/{device}/position', [NetworkMapController::class, 'moveDevice'])->name('map.devices.move');

    Route::post('tunnels', [TunnelController::class, 'store'])->name('tunnels.store');
    Route::patch('tunnels/{tunnel}', [TunnelController::class, 'update'])->name('tunnels.update');
    Route::delete('tunnels/{tunnel}', [TunnelController::class, 'destroy'])->name('tunnels.destroy');

    Route::post('vlans/copy', [VlanController::class, 'copy'])->name('vlans.copy');
    Route::resource('vlans', VlanController::class)->except(['create', 'edit', 'show']);
});

require __DIR__.'/settings.php';
