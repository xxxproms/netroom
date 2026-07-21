<?php

use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use App\Models\Vlan;
use Database\Seeders\DeviceModelSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DeviceModelSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');
});

/**
 * A tiny stand-in for the department's workbook: an "SW" registry and two
 * matrix sheets — one for each complex — where VLAN membership is a fill
 * colour. Blue is tagged (and, on the header row, an uplink); green is
 * untagged; an empty cell is "not on this switch".
 */
function fixtureWorkbook(): UploadedFile
{
    $blue = 'FF0F9ED5';
    $green = 'FF00B050';

    $book = new Spreadsheet;

    $registry = $book->getActiveSheet();
    $registry->setTitle('SW');
    $registry->fromArray([
        ['#', 'Название', 'Лист', 'Модель', 'IP'],
        [1, 'AS LAN 1', 'MS101', 'DES-1210-10/ME', '10.40.0.11'],
        [2, 'BB LAN 1', 'MS201', 'DES-1210-10/ME', '10.40.0.21'],
    ], null, 'A1');

    foreach (['MS101', 'MS201'] as $name) {
        $sheet = $book->createSheet();
        $sheet->setTitle($name);
        // Header: two label columns, then port numbers 1..4.
        $sheet->fromArray([['VLAN', 'Имя', 1, 2, 3, 4]], null, 'A1');
        // VLAN 10 on ports, VLAN 20 on ports.
        $sheet->fromArray([[10, 'Users'], [20, 'Voice']], null, 'A2');

        // Port 4 is an uplink trunk: blue on the header row.
        paint($sheet, 'F1', $blue);
        // VLAN 10 untagged on ports 1-2, tagged on the uplink.
        paint($sheet, 'C2', $green);
        paint($sheet, 'D2', $green);
        paint($sheet, 'F2', $blue);
        // VLAN 20 untagged on port 3, tagged on the uplink.
        paint($sheet, 'E3', $green);
        paint($sheet, 'F3', $blue);
    }

    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'Switch.xlsx', null, null, true);
}

function paint(Worksheet $sheet, string $cell, string $argb): void
{
    $sheet->getStyle($cell)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($argb);
}

test('the preview reports what the workbook would create without writing anything', function () {
    actingAs($this->engineer)
        ->post('/import/preview', ['file' => fixtureWorkbook()])
        ->assertInertia(fn ($page) => $page
            ->component('import/Preview')
            ->where('counts.switches', 2)
            ->where('counts.vlans', 2)
            ->where('counts.memberships', 10)
            ->has('token'));

    expect(Device::count())->toBe(0)
        ->and(Vlan::count())->toBe(0);
});

test('a committed import creates the switches, ports, VLANs, and memberships', function () {
    $preview = actingAs($this->engineer)
        ->post('/import/preview', ['file' => fixtureWorkbook()]);

    $token = $preview->viewData('page')['props']['token'];

    actingAs($this->engineer)
        ->post('/import/commit', ['token' => $token])
        ->assertRedirect('/devices');

    expect(Device::count())->toBe(2)
        ->and(Vlan::count())->toBe(2);

    $as = Device::where('name', 'AS LAN 1')->firstOrFail();

    // The device takes the model's full port complement (a DES-1210-10/ME has
    // ten); the sheet documents membership on only the first four.
    expect($as->ports()->count())->toBe(10)
        ->and($as->ports()->where('is_uplink', true)->pluck('number')->all())->toBe([4]);

    // Port 1 carries VLAN 10 untagged; the uplink carries both tagged.
    $port1 = $as->ports()->where('number', 1)->firstOrFail();
    $uplink = $as->ports()->where('number', 4)->firstOrFail();

    $modes = fn (Port $port) => $port->vlans()->get()
        ->mapWithKeys(fn (Vlan $vlan) => [$vlan->vid => $vlan->pivot->mode])
        ->all();

    expect($modes($port1))->toBe([10 => 'untagged'])
        ->and($modes($uplink))->toBe([10 => 'tagged', 20 => 'tagged']);
});

test('re-running the import skips switches already brought in', function () {
    $commit = function () {
        $preview = actingAs($this->engineer)->post('/import/preview', ['file' => fixtureWorkbook()]);
        $token = $preview->viewData('page')['props']['token'];
        actingAs($this->engineer)->post('/import/commit', ['token' => $token]);
    };

    $commit();
    $commit();

    expect(Device::count())->toBe(2);
});

test('a technician cannot reach the importer', function () {
    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    actingAs($technician)->get('/import')->assertForbidden();
    actingAs($technician)
        ->post('/import/preview', ['file' => fixtureWorkbook()])
        ->assertForbidden();
});

test('a non-spreadsheet upload is refused', function () {
    actingAs($this->engineer)
        ->from('/import')
        ->post('/import/preview', [
            'file' => UploadedFile::fake()->create('notes.txt', 8, 'text/plain'),
        ])
        ->assertSessionHasErrors('file');
});

test('the export hands back a spreadsheet', function () {
    actingAs($this->engineer)
        ->get('/export')
        ->assertOk()
        ->assertDownload()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('a technician cannot export', function () {
    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    actingAs($technician)->get('/export')->assertForbidden();
});
