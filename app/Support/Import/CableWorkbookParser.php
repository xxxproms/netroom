<?php

namespace App\Support\Import;

use App\Models\Cable;
use App\Models\Outlet;
use App\Models\Port;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the "Commutation" sheet of a NetRoom export back in — the return leg of
 * the cabling round-trip, so colours, labels, lengths and status edited in
 * Excel land back in the panel. Ends are matched by the names the export wrote:
 * device + port for equipment, workplace + socket for a wall outlet. Every row
 * is classified (create a run, update the one already there, or skip with a
 * reason) without writing anything; ApplyCableImport does the saving.
 */
class CableWorkbookParser
{
    /** The "Commutation" sheet columns, 1-based, as InventoryExport writes them. */
    private const COL_LABEL = 1;

    private const COL_DEVICE_A = 2;

    private const COL_PORT_A = 3;

    private const COL_DEVICE_B = 4;

    private const COL_SOCKET_B = 5;

    private const COL_MEDIA = 6;

    private const COL_STRANDS = 7;

    private const COL_LENGTH = 8;

    private const COL_COLOUR = 9;

    private const COL_STATUS = 10;

    public function __construct(private readonly SiteContext $context) {}

    /**
     * Whether a workbook is a cabling round-trip rather than the department's
     * hand-kept switch registry: only our own export carries a Commutation sheet.
     */
    public static function handles(string $path): bool
    {
        return in_array('Commutation', IOFactory::createReaderForFile($path)->listWorksheetNames($path), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $path): array
    {
        $sheet = IOFactory::createReader('Xlsx')->load($path)->getSheetByName('Commutation');

        $ports = $this->portIndex();
        $outlets = $this->outletIndex();
        $endToCable = $this->cableIndex();

        $rows = [];

        if ($sheet instanceof Worksheet) {
            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                $parsed = $this->row($sheet, $row, $ports, $outlets, $endToCable);

                if ($parsed !== null) {
                    $rows[] = $parsed;
                }
            }
        }

        return [
            'rows' => $rows,
            'counts' => [
                'create' => $this->count($rows, 'create'),
                'update' => $this->count($rows, 'update'),
                'skip' => $this->count($rows, 'skip'),
                'total' => count($rows),
            ],
        ];
    }

    /**
     * @param  array<string, list<array{id: int, site_id: int, media: string}>>  $ports
     * @param  array<string, list<array{id: int, media: string}>>  $outlets
     * @param  array<string, int>  $endToCable  end key → the cable plugged into it
     * @return array<string, mixed>|null
     */
    private function row(Worksheet $sheet, int $row, array $ports, array $outlets, array $endToCable): ?array
    {
        $deviceA = $this->text($sheet, self::COL_DEVICE_A, $row);
        $socketA = $this->text($sheet, self::COL_PORT_A, $row);
        $ownerB = $this->text($sheet, self::COL_DEVICE_B, $row);
        $socketB = $this->text($sheet, self::COL_SOCKET_B, $row);

        // A wholly blank row is spacing, not an instruction.
        if ($deviceA === null && $socketA === null && $ownerB === null && $socketB === null) {
            return null;
        }

        $label = $this->text($sheet, self::COL_LABEL, $row);
        $a = $this->resolvePort($deviceA, $socketA, $ports);
        $b = $this->resolveEnd($ownerB, $socketB, $ports, $outlets);

        $line = [
            'label' => $label,
            'a' => $this->endLabel($deviceA, $socketA),
            'b' => $this->endLabel($ownerB, $socketB),
            'action' => 'skip',
            'reason' => null,
            '_a' => null,
            '_b' => null,
            '_cable_id' => null,
            '_site_id' => null,
        ];

        // Resolve both ends before deciding anything; a row that names something
        // the panel does not have is reported, never guessed at.
        $failure = $this->resolutionFailure($a, $b);

        if ($failure !== null) {
            return [...$line, 'reason' => $failure, ...$this->appearance($sheet, $row, null, null)];
        }

        /** @var array{id: int, media: string, site_id?: int, type: string} $a */
        /** @var array{id: int, media: string, site_id?: int, type: string} $b */
        if ($a['type'] === $b['type'] && $a['id'] === $b['id']) {
            return [...$line, 'reason' => 'same_end', ...$this->appearance($sheet, $row, $a['media'], $b['media'])];
        }

        $keyA = $a['type'].':'.$a['id'];
        $keyB = $b['type'].':'.$b['id'];
        $cableA = $endToCable[$keyA] ?? null;
        $cableB = $endToCable[$keyB] ?? null;

        $line = [
            ...$line,
            '_a' => ['type' => $a['type'], 'id' => $a['id']],
            '_b' => ['type' => $b['type'], 'id' => $b['id']],
            '_site_id' => $a['site_id'] ?? $b['site_id'] ?? null,
            ...$this->appearance($sheet, $row, $a['media'], $b['media']),
        ];

        // The same run already joining these very ends — refresh how it looks.
        if ($cableA !== null && $cableA === $cableB) {
            return [...$line, 'action' => 'update', '_cable_id' => $cableA];
        }

        // Either end is patched to something else — leave it, don't reroute.
        if ($cableA !== null) {
            return [...$line, 'reason' => 'a_busy'];
        }

        if ($cableB !== null) {
            return [...$line, 'reason' => 'b_busy'];
        }

        return [...$line, 'action' => 'create'];
    }

    /**
     * The cable's look drawn from the sheet, media inferred from the ports when
     * the cell is blank or unknown so a hand-typed row still lands sensibly.
     *
     * @return array{media: string, color: string|null, status: string, _strands: int|null, _length: int|null}
     */
    private function appearance(Worksheet $sheet, int $row, ?string $mediaA, ?string $mediaB): array
    {
        $media = $this->text($sheet, self::COL_MEDIA, $row);
        $media = in_array($media, Cable::MEDIA, true) ? $media : $this->inferMedia($mediaA, $mediaB);

        $status = $this->text($sheet, self::COL_STATUS, $row);
        $status = in_array($status, Cable::STATUSES, true) ? $status : 'connected';

        $color = $this->text($sheet, self::COL_COLOUR, $row);
        $color = $color !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : null;

        return [
            'media' => $media,
            'color' => $color,
            'status' => $status,
            '_strands' => $this->strands($sheet, $row, $media),
            '_length' => $this->length($sheet, $row),
        ];
    }

    /** Copper if both ends are copper; any fibre end makes the run fibre. */
    private function inferMedia(?string $a, ?string $b): string
    {
        $copper = fn (?string $media): bool => $media === 'rj45';

        return $copper($a) && $copper($b) ? 'utp' : 'fibre';
    }

    private function strands(Worksheet $sheet, int $row, string $media): ?int
    {
        if ($media !== 'fibre') {
            return null;
        }

        $value = $sheet->getCell([self::COL_STRANDS, $row])->getValue();

        return in_array((int) $value, Cable::STRANDS, true) ? (int) $value : 2;
    }

    private function length(Worksheet $sheet, int $row): ?int
    {
        $value = $sheet->getCell([self::COL_LENGTH, $row])->getValue();

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Why a row can't be acted on, or null when both ends were found the once.
     *
     * @param  array<string, mixed>|string|null  $a
     * @param  array<string, mixed>|string|null  $b
     */
    private function resolutionFailure(array|string|null $a, array|string|null $b): ?string
    {
        return match (true) {
            is_string($a) => $a,
            is_string($b) => $b,
            $a === null => 'unknown_a',
            $b === null => 'unknown_b',
            default => null,
        };
    }

    /**
     * A switch/panel/server port by device and port name. Returns the match, the
     * string 'ambiguous_a' when the name is not unique across reachable sites,
     * or null when nothing matches.
     *
     * @param  array<string, list<array{id: int, site_id: int, media: string}>>  $ports
     * @return array{type: string, id: int, site_id: int, media: string}|string|null
     */
    private function resolvePort(?string $device, ?string $port, array $ports): array|string|null
    {
        $matches = $ports[$this->key($device, $port)] ?? [];

        return match (count($matches)) {
            0 => null,
            1 => ['type' => $this->morph(Port::class), 'id' => $matches[0]['id'], 'site_id' => $matches[0]['site_id'], 'media' => $matches[0]['media']],
            default => 'ambiguous_a',
        };
    }

    /**
     * The far end, which may be a device port or a wall outlet. A name that hits
     * both, or hits either kind more than once, is ambiguous and left alone.
     *
     * @param  array<string, list<array{id: int, site_id: int, media: string}>>  $ports
     * @param  array<string, list<array{id: int, media: string}>>  $outlets
     * @return array{type: string, id: int, site_id?: int, media: string}|string|null
     */
    private function resolveEnd(?string $owner, ?string $socket, array $ports, array $outlets): array|string|null
    {
        $portMatches = $ports[$this->key($owner, $socket)] ?? [];
        $outletMatches = $outlets[$this->key($owner, $socket)] ?? [];

        $hits = count($portMatches) + count($outletMatches);

        if ($hits === 0) {
            return null;
        }

        if ($hits > 1) {
            return 'ambiguous_b';
        }

        if ($portMatches !== []) {
            return ['type' => $this->morph(Port::class), 'id' => $portMatches[0]['id'], 'site_id' => $portMatches[0]['site_id'], 'media' => $portMatches[0]['media']];
        }

        return ['type' => $this->morph(Outlet::class), 'id' => $outletMatches[0]['id'], 'media' => $outletMatches[0]['media']];
    }

    /**
     * Ports the user may reach, keyed by "device name\0port name". A key can hold
     * several ports — the same name in two complexes — which is how ambiguity is
     * caught rather than a wrong end silently chosen.
     *
     * @return array<string, list<array{id: int, site_id: int, media: string}>>
     */
    private function portIndex(): array
    {
        $index = [];

        Port::query()
            ->whereHas('device', fn ($query) => $this->context->scope($query))
            ->with('device:id,name,site_id')
            ->get()
            ->each(function (Port $port) use (&$index): void {
                if ($port->device === null) {
                    return;
                }

                $index[$this->key($port->device->name, $port->name)][] = [
                    'id' => $port->id,
                    'site_id' => $port->device->site_id,
                    'media' => $port->media,
                ];
            });

        return $index;
    }

    /**
     * @return array<string, list<array{id: int, media: string}>>
     */
    private function outletIndex(): array
    {
        $index = [];

        Outlet::query()
            ->whereHas('workplace', fn ($query) => $this->context->scope($query))
            ->with('workplace:id,name')
            ->get()
            ->each(function (Outlet $outlet) use (&$index): void {
                if ($outlet->workplace === null) {
                    return;
                }

                $index[$this->key($outlet->workplace->name, $outlet->label)][] = [
                    'id' => $outlet->id,
                    'media' => $outlet->media,
                ];
            });

        return $index;
    }

    /**
     * Which cable, if any, occupies each end — so an existing run is refreshed
     * and a busy port is never quietly rewired.
     *
     * @return array<string, int> end key → cable id
     */
    private function cableIndex(): array
    {
        $endToCable = [];

        $this->context->scope(Cable::query())
            ->get(['id', 'a_type', 'a_id', 'b_type', 'b_id'])
            ->each(function (Cable $cable) use (&$endToCable): void {
                $endToCable[$cable->a_type.':'.$cable->a_id] = $cable->id;
                $endToCable[$cable->b_type.':'.$cable->b_id] = $cable->id;
            });

        return $endToCable;
    }

    /**
     * The morph alias a cable end is stored under ("port"/"outlet"), so keys
     * match the a_type/b_type in the database rather than the class name.
     *
     * @param  class-string<Model>  $class
     */
    private function morph(string $class): string
    {
        return (new $class)->getMorphClass();
    }

    private function key(?string $owner, ?string $socket): string
    {
        return mb_strtolower(trim((string) $owner))."\0".mb_strtolower(trim((string) $socket));
    }

    private function endLabel(?string $owner, ?string $socket): string
    {
        return trim(($owner ?? '—').' · '.($socket ?? '—'));
    }

    private function text(Worksheet $sheet, int $column, int $row): ?string
    {
        $value = $sheet->getCell([$column, $row])->getValue();

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function count(array $rows, string $action): int
    {
        return count(array_filter($rows, fn (array $row) => $row['action'] === $action));
    }
}
