<?php

declare(strict_types=1);

namespace App\Exports\TimeEntries;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Shared visual language for the two xlsx exports (spec 0122, D-12, MT-B5):
 * the color palette, fonts and row heights replicate qnet's PhpSpreadsheet
 * report mensile layout (`WorkActivityService::populateMonthlyReportWorksheet`
 * in the legacy backend), reused verbatim here for the filtered export too
 * so both documents read as one family — built entirely from code, no xlsx
 * template/logo (D-12).
 */
final class TimeEntryExportStyles
{
    private const string COLOR_TITLE_BG = '1F4E79';

    private const string COLOR_SUBTITLE_BG = 'D6E4F0';

    private const string COLOR_HEADER_BG = '2E75B6';

    private const string COLOR_ZEBRA_EVEN = 'FFFFFF';

    private const string COLOR_ZEBRA_ODD = 'EBF3FB';

    private const string COLOR_BORDER = 'CCCCCC';

    private const string COLOR_WHITE = 'FFFFFF';

    private const string FONT_NAME = 'Arial';

    public const int TITLE_ROW_HEIGHT = 28;

    public const int SUBTITLE_ROW_HEIGHT = 20;

    public const int HEADER_ROW_HEIGHT = 18;

    public const int TOTAL_ROW_HEIGHT = 20;

    public static function applyTitle(Worksheet $worksheet, string $range): void
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_WHITE], 'name' => self::FONT_NAME],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TITLE_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    public static function applySubtitle(Worksheet $worksheet, string $range): void
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'name' => self::FONT_NAME],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_SUBTITLE_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
    }

    public static function applySectionHeader(Worksheet $worksheet, string $range): void
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COLOR_WHITE], 'name' => self::FONT_NAME],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    public static function applyTableHeader(Worksheet $worksheet, string $range): void
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_WHITE], 'name' => self::FONT_NAME, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_WHITE]]],
        ]);
    }

    public static function applyBodyRow(Worksheet $worksheet, string $range, bool $isEvenRow): void
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => ['name' => self::FONT_NAME, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $isEvenRow ? self::COLOR_ZEBRA_EVEN : self::COLOR_ZEBRA_ODD]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => self::COLOR_BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
    }

    public static function applyTotalRow(Worksheet $worksheet, string $range): void
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'name' => self::FONT_NAME, 'size' => 10, 'color' => ['rgb' => self::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TITLE_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_WHITE]]],
        ]);
    }
}
