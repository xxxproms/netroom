<?php

namespace App\Support\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gives every exported sheet the same finished look: a dark header the eye
 * lands on, a frozen top row, an auto-filter, sized columns, thin gridlines,
 * and status cells tinted the way they are on screen. The counterpart to the
 * styled tables in the app, so the workbook does not read as raw text.
 */
final class SheetStyler
{
    /** Status → cell fill, matching the on-screen chips. */
    private const STATUS_FILL = [
        'connected' => 'FFDCFCE7',
        'active' => 'FFDCFCE7',
        'planned' => 'FFFEF3C7',
        'spare' => 'FFDBEAFE',
        'failed' => 'FFFEE2E2',
        'decommissioned' => 'FFF1F5F9',
    ];

    /**
     * @param  int  $columns  How many columns the sheet uses (1-based count).
     * @param  int  $rows  How many data rows sit below the header.
     * @param  int|null  $statusColumn  1-based column whose cells to tint, if any.
     */
    public static function apply(Worksheet $sheet, int $columns, int $rows, ?int $statusColumn = null): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($columns);
        $lastRow = $rows + 1;

        // A dark header with white, bold text.
        $header = "A1:{$lastCol}1";
        $sheet->getStyle($header)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($header)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1E293B');
        $sheet->getStyle($header)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter($header);

        for ($column = 1; $column <= $columns; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        if ($rows > 0) {
            $sheet->getStyle("A1:{$lastCol}{$lastRow}")->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFE2E8F0');

            if ($statusColumn !== null) {
                self::tintStatus($sheet, $statusColumn, $lastRow);
            }
        }
    }

    /**
     * Fill each status cell by its value, so the column reads at a glance.
     */
    private static function tintStatus(Worksheet $sheet, int $column, int $lastRow): void
    {
        $letter = Coordinate::stringFromColumnIndex($column);

        for ($row = 2; $row <= $lastRow; $row++) {
            $value = (string) $sheet->getCell("{$letter}{$row}")->getValue();
            $fill = self::STATUS_FILL[$value] ?? null;

            if ($fill !== null) {
                $sheet->getStyle("{$letter}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($fill);
            }
        }
    }
}
