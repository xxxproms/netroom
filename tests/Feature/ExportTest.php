<?php

use App\Models\Cable;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->site = Site::factory()->create();
    $this->room = Room::factory()->create(['site_id' => $this->site->id]);
    $this->rack = Rack::factory()->create(['room_id' => $this->room->id]);
});

/** Download the export the way a user would and read it back. */
function loadExport(): Spreadsheet
{
    $content = test()->get('/export')->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($path, $content);

    return IOFactory::load($path);
}

test('the export carries a commutation sheet listing every cable', function () {
    $switch = Device::factory()->create([
        'site_id' => $this->site->id, 'rack_id' => $this->rack->id,
        'device_model_id' => DeviceModel::factory()->create(['kind' => 'switch'])->id,
        'name' => 'SW-1',
    ]);
    $port = $switch->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);

    $panel = Device::factory()->create([
        'site_id' => $this->site->id, 'rack_id' => $this->rack->id,
        'device_model_id' => DeviceModel::factory()->create(['kind' => 'patch_panel'])->id,
        'name' => 'PP-1',
    ]);
    $front = $panel->ports()->create(['name' => 'F1', 'number' => 1, 'media' => 'rj45', 'role' => 'front']);

    Cable::factory()->between($port, $front)->create([
        'site_id' => $this->site->id, 'label' => 'A-01', 'media' => 'utp', 'status' => 'connected',
    ]);

    $this->actingAs($this->engineer);
    $sheet = loadExport()->getSheetByName('Commutation');

    expect($sheet)->not->toBeNull()
        ->and($sheet->getCell('A1')->getValue())->toBe('Label')
        ->and($sheet->getCell('A2')->getValue())->toBe('A-01')
        ->and($sheet->getCell('B2')->getValue())->toBe('SW-1')
        ->and((string) $sheet->getCell('C2')->getValue())->toBe('1')
        ->and($sheet->getCell('D2')->getValue())->toBe('PP-1')
        ->and($sheet->getCell('E2')->getValue())->toBe('F1')
        ->and($sheet->getCell('J2')->getValue())->toBe('connected');
});

test('exported sheets are styled, not raw text', function () {
    $this->actingAs($this->engineer);
    $sheet = loadExport()->getSheetByName('Commutation');

    // A dark, bold header and a frozen top row read as a finished table.
    expect($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('A1')->getFill()->getStartColor()->getARGB())->toBe('FF1E293B')
        ->and($sheet->getFreezePane())->toBe('A2')
        ->and($sheet->getAutoFilter()->getRange())->not->toBe('');
});
