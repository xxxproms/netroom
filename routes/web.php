<?php

use App\Http\Controllers\DeviceModelController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SiteContextController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\VlanController;
use App\Http\Controllers\VlanDomainController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // The site picker in the header.
    Route::put('context/site', [SiteContextController::class, 'update'])->name('context.site');

    Route::resource('sites', SiteController::class)->except(['create', 'edit']);
    Route::resource('rooms', RoomController::class)->except(['create', 'edit']);
    Route::resource('racks', RackController::class)->except(['create', 'edit', 'show']);
    Route::resource('device-models', DeviceModelController::class)->except(['create', 'edit', 'show']);
    Route::resource('vlan-domains', VlanDomainController::class)->except(['create', 'edit', 'show']);

    Route::post('vlans/copy', [VlanController::class, 'copy'])->name('vlans.copy');
    Route::resource('vlans', VlanController::class)->except(['create', 'edit', 'show']);
});

require __DIR__.'/settings.php';
