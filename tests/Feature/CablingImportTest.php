<?php

use App\Models\Cable;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Outlet;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->site = Site::factory()->create();
    $this->room = Room::factory()->create(['site_id' => $this->site->id]);
    $this->rack = Rack::factory()->create(['room_id' => $this->room->id]);

    $this->switch = Device::factory()->create([
        'site_id' => $this->site->id, 'rack_id' => $this->rack->id, 'name' => 'SW-1',
        'device_model_id' => DeviceModel::factory()->create(['kind' => 'switch'])->id,
    ]);
    $this->p1 = $this->switch->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);
    $this->p2 = $this->switch->ports()->create(['name' => '2', 'number' => 2, 'media' => 'rj45', 'role' => 'network']);

    $this->panel = Device::factory()->create([
        'site_id' => $this->site->id, 'rack_id' => $this->rack->id, 'name' => 'PP-1',
        'device_model_id' => DeviceModel::factory()->create(['kind' => 'patch_panel'])->id,
    ]);
    $this->f1 = $this->panel->ports()->create(['name' => 'F1', 'number' => 1, 'media' => 'rj45', 'role' => 'front']);
    $this->f2 = $this->panel->ports()->create(['name' => 'F2', 'number' => 2, 'media' => 'rj45', 'role' => 'front']);
});

/**
 * A workbook shaped like the "Commutation" sheet the export writes: ten columns,
 * one row per cable, ends named by device+port (or workplace+socket).
 *
 * @param  list<array<int, string|int|null>>  $rows
 */
function commutationUpload(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Commutation');
    $sheet->fromArray([[
        'Label', 'Device A', 'Port A', 'Device / workplace B', 'Port / socket B',
        'Media', 'Strands', 'Length cm', 'Colour', 'Status',
    ]], null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'cab').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'netroom-export.xlsx', null, null, true);
}

/** Run a sheet all the way through preview and commit. */
function importCabling(User $user, array $rows): void
{
    $preview = actingAs($user)
        ->post('/import/preview', ['file' => commutationUpload($rows)]);

    $token = $preview->viewData('page')['props']['token'];

    actingAs($user)->post('/import/commit', ['token' => $token]);
}

test('a commutation sheet is recognised and previewed as cabling, not switches', function () {
    actingAs($this->engineer)
        ->post('/import/preview', [
            'file' => commutationUpload([
                ['A-01', 'SW-1', '1', 'PP-1', 'F1', 'utp', null, 200, '#0284c7', 'connected'],
            ]),
        ])
        ->assertInertia(fn ($page) => $page
            ->component('import/Cabling')
            ->where('counts.create', 1)
            ->where('counts.update', 0)
            ->where('rows.0.action', 'create')
            ->has('token'));

    // Preview writes nothing.
    expect(Cable::count())->toBe(0);
});

test('a row between two free ports lays a new cable', function () {
    importCabling($this->engineer, [
        ['A-01', 'SW-1', '1', 'PP-1', 'F1', 'utp', null, 200, '#0284c7', 'connected'],
    ]);

    expect(Cable::count())->toBe(1);

    $cable = Cable::first();
    expect($cable->media)->toBe('utp')
        ->and($cable->color)->toBe('#0284c7')
        ->and($cable->status)->toBe('connected')
        ->and($cable->length_cm)->toBe(200)
        ->and($cable->label)->toBe('A-01')
        ->and($cable->a->is($this->p1) || $cable->b->is($this->p1))->toBeTrue()
        ->and($cable->a->is($this->f1) || $cable->b->is($this->f1))->toBeTrue();
});

test('re-importing an edited row refreshes the same cable in place', function () {
    $cable = Cable::factory()->between($this->p1, $this->f1)->create([
        'site_id' => $this->site->id, 'label' => 'A-01', 'media' => 'utp',
        'color' => '#000000', 'status' => 'planned',
    ]);

    importCabling($this->engineer, [
        ['A-01', 'SW-1', '1', 'PP-1', 'F1', 'utp', null, 300, '#e11d48', 'connected'],
    ]);

    // No second cable — the run in place was refreshed.
    expect(Cable::count())->toBe(1);
    $cable->refresh();
    expect($cable->color)->toBe('#e11d48')
        ->and($cable->status)->toBe('connected')
        ->and($cable->length_cm)->toBe(300);
});

test('a row whose port is already patched elsewhere is skipped', function () {
    // Port 1 is taken by an existing run to F1.
    Cable::factory()->between($this->p1, $this->f1)->create([
        'site_id' => $this->site->id, 'media' => 'utp', 'status' => 'connected',
    ]);

    actingAs($this->engineer)
        ->post('/import/preview', [
            'file' => commutationUpload([
                // Tries to rewire port 1 to F2 — must not touch the busy port.
                ['B-01', 'SW-1', '1', 'PP-1', 'F2', 'utp', null, null, null, 'connected'],
            ]),
        ])
        ->assertInertia(fn ($page) => $page
            ->where('counts.create', 0)
            ->where('counts.skip', 1)
            ->where('rows.0.action', 'skip')
            ->where('rows.0.reason', 'a_busy'));

    expect(Cable::count())->toBe(1);
});

test('a row naming an unknown end is skipped with a reason', function () {
    actingAs($this->engineer)
        ->post('/import/preview', [
            'file' => commutationUpload([
                ['C-01', 'SW-1', '1', 'GHOST', 'Z9', 'utp', null, null, null, 'connected'],
            ]),
        ])
        ->assertInertia(fn ($page) => $page
            ->where('counts.skip', 1)
            ->where('rows.0.action', 'skip')
            ->where('rows.0.reason', 'unknown_b'));
});

test('a cable to a wall outlet is laid by workplace and socket name', function () {
    $workplace = Workplace::factory()->create(['site_id' => $this->site->id, 'name' => 'Room 12']);
    $outlet = Outlet::factory()->create(['workplace_id' => $workplace->id, 'label' => 'A', 'media' => 'rj45']);

    importCabling($this->engineer, [
        ['D-01', 'SW-1', '1', 'Room 12', 'A', 'utp', null, null, null, 'connected'],
    ]);

    expect(Cable::count())->toBe(1);
    $cable = Cable::first();
    expect($cable->a->is($outlet) || $cable->b->is($outlet))->toBeTrue();
});

test('a real export round-trips: its Commutation sheet re-imports as an update', function () {
    // A cable in place, exported the way a user downloads it.
    Cable::factory()->between($this->p1, $this->f1)->create([
        'site_id' => $this->site->id, 'label' => 'RT-1', 'media' => 'utp',
        'color' => '#0284c7', 'status' => 'connected',
    ]);

    $content = actingAs($this->engineer)->get('/export')->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'rt').'.xlsx';
    file_put_contents($path, $content);
    $upload = new UploadedFile($path, 'netroom-export.xlsx', null, null, true);

    // Fed straight back, the export's own columns line up with the parser and
    // the existing run is recognised — proof the two halves agree.
    actingAs($this->engineer)
        ->post('/import/preview', ['file' => $upload])
        ->assertInertia(fn ($page) => $page
            ->component('import/Cabling')
            ->where('counts.update', 1)
            ->where('counts.create', 0)
            ->where('rows.0.action', 'update')
            ->where('rows.0.label', 'RT-1'));
});

test('a technician cannot import cabling', function () {
    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    actingAs($technician)
        ->post('/import/preview', [
            'file' => commutationUpload([
                ['E-01', 'SW-1', '1', 'PP-1', 'F1', 'utp', null, null, null, 'connected'],
            ]),
        ])
        ->assertForbidden();
});
