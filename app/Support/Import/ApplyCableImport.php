<?php

namespace App\Support\Import;

use App\Models\Cable;
use Illuminate\Support\Facades\DB;

/**
 * Writes a parsed Commutation sheet back into the panel: refreshes the look of
 * runs already in place and lays new ones for rows the preview marked to create.
 * Skips are left untouched, so it is safe to preview, fix the sheet, and re-run.
 */
class ApplyCableImport
{
    /**
     * @param  array<string, mixed>  $parsed  the output of CableWorkbookParser
     * @return array<string, int>
     */
    public function apply(array $parsed): array
    {
        return DB::transaction(function () use ($parsed) {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            // Ends already claimed by an earlier row this run — a duplicated line
            // in the sheet must not double-patch the same port.
            $taken = [];

            foreach ($parsed['rows'] as $row) {
                if ($row['action'] === 'update') {
                    $this->refresh($row);
                    $updated++;

                    continue;
                }

                if ($row['action'] !== 'create') {
                    $skipped++;

                    continue;
                }

                $keyA = $row['_a']['type'].':'.$row['_a']['id'];
                $keyB = $row['_b']['type'].':'.$row['_b']['id'];

                if (isset($taken[$keyA]) || isset($taken[$keyB])) {
                    $skipped++;

                    continue;
                }

                $this->lay($row);
                $taken[$keyA] = $taken[$keyB] = true;
                $created++;
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function lay(array $row): void
    {
        Cable::create([
            'site_id' => $row['_site_id'],
            'a_type' => $row['_a']['type'],
            'a_id' => $row['_a']['id'],
            'b_type' => $row['_b']['type'],
            'b_id' => $row['_b']['id'],
            ...$this->look($row),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function refresh(array $row): void
    {
        $cable = Cable::find((int) $row['_cable_id']);

        $cable?->update($this->look($row));
    }

    /**
     * The fields a row carries over — everything but the two ends.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function look(array $row): array
    {
        return [
            'media' => $row['media'],
            'strands' => $row['_strands'],
            'label' => $row['label'],
            'length_cm' => $row['_length'],
            'color' => $row['color'],
            'status' => $row['status'],
        ];
    }
}
