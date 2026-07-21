<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the "Switch AS-BB.xlsx" the department keeps by hand into something the
 * panel can show and then save. The registry sheet "SW" lists the switches; a
 * sheet per switch (MSxxx) holds a VLAN × port grid where membership is a cell
 * colour. Nothing is written here — this only reports what an import would do.
 */
class SwitchWorkbookParser
{
    public function __construct(private readonly FillClassifier $fills = new FillClassifier) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($path);

        $switches = [];
        $warnings = [];
        $vlanNames = [];

        foreach ($this->registry($spreadsheet) as $entry) {
            $sheet = $entry['sheet'] !== null
                ? $spreadsheet->getSheetByName($entry['sheet'])
                : null;

            $switch = $this->switchFrom($entry, $sheet, $vlanNames);
            $switches[] = $switch;
            $warnings = [...$warnings, ...$switch['warnings']];
        }

        $vlans = [];
        foreach ($vlanNames as $vid => $name) {
            $vlans[] = ['vid' => $vid, 'name' => $name];
        }
        usort($vlans, fn ($a, $b) => $a['vid'] <=> $b['vid']);

        return [
            'domain' => 'Астория + Бомбей',
            'sites' => [
                ['code' => 'AS', 'name' => 'Астория'],
                ['code' => 'BB', 'name' => 'Бомбей'],
            ],
            'vlans' => $vlans,
            'switches' => $switches,
            'warnings' => array_values($warnings),
            'counts' => [
                'switches' => count($switches),
                'vlans' => count($vlans),
                'memberships' => array_sum(array_map(fn ($s) => count($s['memberships']), $switches)),
                'warnings' => count($warnings),
            ],
        ];
    }

    /**
     * The switch list from the "SW" sheet: number, name, the sheet that holds
     * its matrix, model, and management IP.
     *
     * @return list<array<string, mixed>>
     */
    private function registry(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('SW');

        if (! $sheet instanceof Worksheet) {
            return [];
        }

        $rows = [];

        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $name = $this->text($sheet->getCell([2, $row])->getValue());

            if ($name === null) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'sheet' => $this->text($sheet->getCell([3, $row])->getValue()),
                'model' => $this->text($sheet->getCell([4, $row])->getValue()),
                'mgmt_ip' => $this->text($sheet->getCell([5, $row])->getValue()),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $vlanNames  collected across every sheet, by ref
     * @return array<string, mixed>
     */
    private function switchFrom(array $entry, ?Worksheet $sheet, array &$vlanNames): array
    {
        $warnings = [];
        $name = $entry['name'];

        if ($this->looksGarbled($name)) {
            $warnings[] = ['switch' => $name, 'kind' => 'garbled_name'];
        }

        $siteCode = $this->siteFor($entry['sheet']);

        if ($sheet === null) {
            // A switch listed with no matrix sheet still exists — it just has no
            // documented ports to bring in.
            if ($entry['sheet'] !== null) {
                $warnings[] = ['switch' => $name, 'kind' => 'missing_sheet', 'sheet' => $entry['sheet']];
            } else {
                $warnings[] = ['switch' => $name, 'kind' => 'no_sheet'];
            }

            return [
                'name' => $name,
                'sheet' => $entry['sheet'],
                'model' => $entry['model'],
                'mgmt_ip' => $entry['mgmt_ip'],
                'site' => $siteCode,
                'port_count' => 0,
                'uplinks' => [],
                'memberships' => [],
                'warnings' => $warnings,
            ];
        }

        $ports = $this->portColumns($sheet);
        $uplinks = [];
        $memberships = [];

        foreach ($ports as $column => $port) {
            if ($this->fills->classify($this->argb($sheet, $column, 1)) === FillClassifier::TAGGED) {
                $uplinks[] = $port;
            }
        }

        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $vid = $sheet->getCell([1, $row])->getValue();

            if (! is_numeric($vid)) {
                continue;
            }

            $vid = (int) $vid;
            $vlanName = $this->text($sheet->getCell([2, $row])->getValue());
            $vlanNames[$vid] ??= $vlanName ?? (string) $vid;

            foreach ($ports as $column => $port) {
                $mode = $this->fills->classify($this->argb($sheet, $column, $row));

                if ($mode !== FillClassifier::NONE) {
                    $memberships[] = ['vid' => $vid, 'port' => $port, 'mode' => $mode];
                }
            }
        }

        $expected = $this->modelPortCount($entry['model']);

        if ($expected !== null && count($ports) > $expected) {
            $warnings[] = [
                'switch' => $name,
                'kind' => 'port_count_mismatch',
                'sheet_ports' => count($ports),
                'model_ports' => $expected,
            ];
        }

        return [
            'name' => $name,
            'sheet' => $entry['sheet'],
            'model' => $entry['model'],
            'mgmt_ip' => $entry['mgmt_ip'],
            'site' => $siteCode,
            'port_count' => count($ports),
            'uplinks' => $uplinks,
            'memberships' => $memberships,
            'warnings' => $warnings,
        ];
    }

    /**
     * The port-number columns of a matrix sheet, keyed by column index.
     *
     * @return array<int, int>
     */
    private function portColumns(Worksheet $sheet): array
    {
        $columns = [];
        $highest = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $highest; $column++) {
            $value = $sheet->getCell([$column, 1])->getValue();

            if (is_numeric($value) && (string) (int) $value === (string) $value) {
                $columns[$column] = (int) $value;
            }
        }

        return $columns;
    }

    private function argb(Worksheet $sheet, int $column, int $row): ?string
    {
        $coordinate = Coordinate::stringFromColumnIndex($column).$row;

        return $sheet->getStyle($coordinate)->getFill()->getStartColor()->getARGB();
    }

    /**
     * MS1xx sheets are Астория, MS2xx are Бомбей — the two adjacent complexes.
     */
    private function siteFor(?string $sheet): ?string
    {
        if ($sheet === null || ! preg_match('/MS(\d)/', $sheet, $match)) {
            return null;
        }

        return $match[1] === '2' ? 'BB' : 'AS';
    }

    private function modelPortCount(?string $model): ?int
    {
        return match ($model) {
            'DGS-1210-28/ME', 'DES-1210-28/ME', 'DGS-1100-24', 'DES-1100-24' => 28,
            'DGS-1210-12TS/ME' => 12,
            'DES-1210-10/ME' => 10,
            default => null,
        };
    }

    /**
     * Old exports carry names in a broken encoding. A run of the replacement
     * character or of Latin-1-mangled Cyrillic is the tell.
     */
    private function looksGarbled(string $name): bool
    {
        return str_contains($name, "\u{FFFD}")
            || (bool) preg_match('/[\x{0080}-\x{00BF}]{2,}/u', $name);
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
