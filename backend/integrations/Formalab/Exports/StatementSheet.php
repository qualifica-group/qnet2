<?php

declare(strict_types=1);

namespace Integrations\Formalab\Exports;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Colors, styles and row helpers of the legacy prospetto Excel export
 * (`exportExcel`, `scriviRigaIntestazioneScenario`, `scriviRigaImporto`).
 */
final class StatementSheet
{
    public const string CURRENCY = '€ #.##0,00';

    public const int START_ROW = 5;

    public const string COLOR_FORECAST = 'C6EFCE';

    public const string COLOR_ADVANCE = 'FFEEBA';

    public const string COLOR_FORECAST_BALANCE = 'D4EDDA';

    public const string COLOR_BALANCE = 'B6DFFF';

    public const string COLOR_SINGLE_PAYMENT = 'B8DAFF';

    public const string COLOR_FORECAST_TOTAL = '28A745';

    public const string COLOR_ACTUAL_TOTAL = '1E3A8A';

    public const string COLOR_MOL = 'D1ECF1';

    public const string COLOR_MUTED = '6C757D';

    public const string COLOR_POSITIVE = '28A745';

    public const string COLOR_NEGATIVE = 'DC3545';

    public const string COLOR_COMMISSION_TOTAL = 'FFF3CD';

    public const string COLOR_TOTAL_COSTS = 'E9ECEF';

    public const string COLOR_TOTALS_ROW = 'D6D8DB';

    public const string COLOR_ZEBRA = 'F8F9FA';

    public const string COLOR_PASSED = 'C6EFCE';

    public const string COLOR_FAILED = 'FFCCCC';

    public const string COLOR_UNKNOWN = 'FFEB9C';

    private const int TALL_ROW = 32;

    /**
     * @return array<string, mixed>
     */
    public static function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function titleStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
        ];
    }

    public static function percent(float $weight): string
    {
        return number_format($weight * 100, 1, ',', '.').'%';
    }

    public static function fill(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    public static function money(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::CURRENCY);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    public static function tallRow(Worksheet $sheet, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(self::TALL_ROW);
    }

    /** A scenario heading over columns A-B, with an optional second line. */
    public static function scenarioHeader(Worksheet $sheet, int $row, string $title, string $subtitle, string $color): int
    {
        $sheet->setCellValue('A'.$row, $subtitle !== '' ? $title."\n".$subtitle : $title);
        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->getStyle('A'.$row.':B'.$row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        ]);
        if ($subtitle !== '') {
            self::tallRow($sheet, $row);
        }

        return $row + 1;
    }

    /** A "label | amount" row in columns A-B. */
    public static function amountRow(Worksheet $sheet, int $row, string $label, float $amount, bool $bold, ?string $background = null, ?string $fontColor = null): int
    {
        $sheet->setCellValue('A'.$row, $label);
        $sheet->setCellValue('B'.$row, $amount);
        if ($bold) {
            $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        }
        if ($background !== null) {
            self::fill($sheet, 'A'.$row.':B'.$row, $background);
        }
        if ($fontColor !== null) {
            $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setColor(new Color($fontColor));
        }
        self::money($sheet, 'B'.$row);

        return $row + 1;
    }
}
