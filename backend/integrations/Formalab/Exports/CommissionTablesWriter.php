<?php

declare(strict_types=1);

namespace Integrations\Formalab\Exports;

use Integrations\Formalab\Services\LearnerStatement\StatementCalculator;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The partner commission tables under the learners of the whole statement
 * (`scriviTabellaProvvigioni`, `scriviTabellaProvvigioniTranche`): the
 * forecast one always, then one per tranche of the payment state.
 */
final class CommissionTablesWriter
{
    private const int GAP_AFTER_LEARNERS = 3;

    private const int GAP_BETWEEN_TABLES = 2;

    public function write(Worksheet $sheet, StatementCalculator $calc, int $learnersEndRow, string $lastColumn): void
    {
        $summary = $calc->partnerSummary();
        $row = $learnersEndRow + self::GAP_AFTER_LEARNERS;

        $rows = array_map(static fn (array $p): array => [$p['partner'], $p['learners_count'], $p['total']], $summary);
        $row = $this->table($sheet, $row, $lastColumn, 'PROVVIGIONI SCENARIO PREVISTO', StatementSheet::COLOR_FORECAST, 'Totale Provvigioni', $rows, 'TOTALE:', $calc->totalCommissions());

        $tranches = [];
        if ($calc->isAdvanceOnly() || $calc->isAdvanceAndBalance()) {
            $tranches[] = [$calc->tranche($calc->advance()), 'PROVVIGIONI ACCONTO (peso %s)', StatementSheet::COLOR_ADVANCE];
        }
        if ($calc->isAdvanceOnly()) {
            $tranches[] = [$calc->tranche($calc->forecastBalance()), 'PROVVIGIONI SALDO PREVISTO (peso %s · STIMA)', StatementSheet::COLOR_FORECAST_BALANCE];
        }
        if ($calc->isAdvanceAndBalance()) {
            $tranches[] = [$calc->tranche($calc->balance()), 'PROVVIGIONI SALDO (peso %s)', StatementSheet::COLOR_BALANCE];
        }

        foreach ($tranches as [$tranche, $title, $color]) {
            $rows = array_map(static fn (array $p): array => [
                $p['partner'],
                $p['learners_count'],
                $calc->partnerTrancheCommissions($p, $tranche['profit_per_learner'], $tranche['weight']),
            ], $summary);
            $row = $this->table($sheet, $row + self::GAP_BETWEEN_TABLES, $lastColumn, sprintf($title, StatementSheet::percent($tranche['weight'])), $color, 'Provvigione tranche', $rows, 'TOTALE TRANCHE:', $tranche['commissions']);
        }
    }

    /**
     * @param  list<array{0: string, 1: int, 2: float}>  $rows
     */
    private function table(Worksheet $sheet, int $row, string $lastColumn, string $title, string $color, string $amountHeader, array $rows, string $totalLabel, float $total): int
    {
        $sheet->setCellValue('D'.$row, $title);
        $sheet->mergeCells('D'.$row.':'.$lastColumn.$row);
        $sheet->getStyle('D'.$row.':'.$lastColumn.$row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        ]);
        $row++;

        $sheet->fromArray(['Partner Commerciale', 'N° Discenti', $amountHeader], null, 'D'.$row);
        $sheet->getStyle('D'.$row.':F'.$row)->applyFromArray(StatementSheet::headerStyle());
        $row++;

        foreach ($rows as [$partner, $count, $amount]) {
            $sheet->fromArray([$partner, $count, $amount], null, 'D'.$row);
            $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode(StatementSheet::CURRENCY);
            $sheet->getStyle('D'.$row.':F'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        $sheet->setCellValue('D'.$row, $totalLabel);
        $sheet->mergeCells('D'.$row.':E'.$row);
        $sheet->setCellValue('F'.$row, $total);
        $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode(StatementSheet::CURRENCY);
        $sheet->getStyle('D'.$row.':F'.$row)->getFont()->setBold(true);
        StatementSheet::fill($sheet, 'D'.$row.':F'.$row, StatementSheet::COLOR_COMMISSION_TOTAL);

        return $row;
    }
}
