<?php

declare(strict_types=1);

namespace Integrations\Formalab\Exports;

use Integrations\Formalab\Services\LearnerStatement\LearnerStatement;
use Integrations\Formalab\Services\LearnerStatement\StatementCalculator;
use Integrations\Formalab\Services\LearnerStatement\StatementLearner;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "ELENCO DISCENTI" of the legacy export, from column D: one commission
 * column per applicable scenario (forecast always, advance in states A/C,
 * balance in A/B/C). Whole statement: every learner, with the partner
 * column, commissions only for a passed learner with a partner. Partner
 * sheet: that partner's learners, commissions for the passed ones.
 *
 * @phpstan-type Columns array{advance: bool, balance: bool, balance_label: string}
 */
final class LearnersTableWriter
{
    private const int FIRST_COLUMN = 4;

    private const int TOTALS_LABEL_SPAN = 5;

    private const array WIDTHS = [
        '#' => 5, 'Cognome' => 14, 'Nome' => 14, 'Presenza' => 11, 'Assenze' => 11, 'Superato' => 9,
        'C_€/h' => 7, 'Importo Oda' => 11, 'R_€/h' => 7, 'R_h' => 8, 'R_€ Tot.' => 11,
        'Partner Comm.' => 20, '% Provv.' => 9, 'ODA' => 22,
    ];

    private const int COMMISSION_WIDTH = 15;

    /**
     * @param  list<StatementLearner>  $learners
     * @return array{end_row: int, last_column: string}
     */
    public function write(Worksheet $sheet, LearnerStatement $statement, array $learners, ?string $partner): array
    {
        $calc = $statement->calculator;
        $headers = $this->headers($calc, $partner === null);
        $lastColumn = $this->column(count($headers) - 1);
        $start = StatementSheet::START_ROW;

        // Step 1: title and header row
        $sheet->setCellValue('D'.$start, $partner === null ? 'ELENCO DISCENTI' : 'ELENCO DISCENTI ASSOCIATI A '.mb_strtoupper($partner));
        $sheet->mergeCells('D'.$start.':'.$lastColumn.$start);
        $sheet->getStyle('D'.$start.':'.$lastColumn.$start)->applyFromArray(StatementSheet::titleStyle());
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->column($index).($start + 1), $header);
            $sheet->getColumnDimension($this->column($index))->setWidth(self::WIDTHS[$header] ?? self::COMMISSION_WIDTH);
        }
        $sheet->getStyle('D'.($start + 1).':'.$lastColumn.($start + 1))->applyFromArray(StatementSheet::headerStyle());
        StatementSheet::tallRow($sheet, $start + 1);

        // Step 2: one row per learner
        $row = $start + 2;
        $totals = ['commissions' => [], 'cost' => 0.0, 'revenue' => 0.0];
        $orders = $statement->quote->learnerOrdersBySupplier();
        $percentColumn = array_search('% Provv.', $headers, true);
        foreach (array_values($learners) as $number => $learner) {
            $values = $this->rowValues($statement, $learner, $partner === null, $orders, $number + 1, $totals);
            $this->writeRow($sheet, $row, $values, $lastColumn, ($number + 1) % 2 === 0 ? $this->column((int) $percentColumn) : null);
            $row++;
        }

        // Step 3: totals row
        $this->writeTotals($sheet, $row, $headers, $lastColumn, $partner === null
            ? ['Importo Oda' => $statement->quote->learnerCostsRounded(), 'R_€ Tot.' => $calc->learnerRevenue()] + $totals['commissions']
            : ['Importo Oda' => $totals['cost'], 'R_€ Tot.' => $totals['revenue']] + $totals['commissions']);

        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(2);
        $sheet->freezePane('A'.($start + 2));

        return ['end_row' => $row, 'last_column' => $lastColumn];
    }

    /**
     * @return list<string>
     */
    private function headers(StatementCalculator $calc, bool $withPartner): array
    {
        $headers = ['#', 'Cognome', 'Nome', 'Presenza', 'Assenze', 'Superato', 'C_€/h', 'Importo Oda', 'R_€/h', 'R_h', 'R_€ Tot.'];
        if ($withPartner) {
            $headers[] = 'Partner Comm.';
        }
        array_push($headers, '% Provv.', '€ Provv. Previsto');
        if ($calc->isAdvanceOnly() || $calc->isAdvanceAndBalance()) {
            $headers[] = '€ Provv. Acconto';
        }
        if ($calc->isAdvanceOnly()) {
            $headers[] = '€ Provv. Saldo (prev.)';
        } elseif ($calc->isBalanceOnly() || $calc->isAdvanceAndBalance()) {
            $headers[] = '€ Provv. Saldo';
        }
        $headers[] = 'ODA';

        return $headers;
    }

    /**
     * Cell values of one learner: [value, style hint] pairs in header order.
     *
     * @param  array<int, array{id: int, date: string|null}>  $orders
     * @param  array{commissions: array<string, float>, cost: float, revenue: float}  $totals
     * @return list<array{0: mixed, 1: string|null}>
     */
    private function rowValues(LearnerStatement $statement, StatementLearner $learner, bool $whole, array $orders, int $number, array &$totals): array
    {
        $calc = $statement->calculator;
        $passed = $learner->passed === true;
        $revenueHours = (float) $learner->revenueHours;
        $wholeHours = (int) floor($revenueHours);

        $values = [
            [$number, null], [$learner->lastName(), null], [$learner->firstName(), null],
            [$learner->int('ore_presenza').'h '.$learner->int('minuti_presenza').'m', null],
            [$learner->int('ore_assenza').'h '.$learner->int('minuti_assenza').'m', null],
            [$learner->passed === true ? 'SI' : ($learner->passed === false ? 'NO' : '??'), $learner->passed === true ? StatementSheet::COLOR_PASSED : ($learner->passed === false ? StatementSheet::COLOR_FAILED : StatementSheet::COLOR_UNKNOWN)],
            [$statement->rates->costPerHour(), 'money'],
            [$passed ? $learner->cost : '', $passed ? 'money' : null],
            [$statement->rates->revenuePerHour(), 'money'],
            [$wholeHours.'h '.(int) round(($revenueHours - $wholeHours) * 60).'m', 'center'],
            [$learner->revenue, 'money'],
        ];
        if ($whole) {
            $values[] = [$learner->partner ?? '-', null];
        }
        $values[] = [$learner->percentage > 0 ? $learner->percentage.'%' : '-', 'center'];

        if ($passed) {
            $totals['cost'] += $learner->cost;
        }
        $totals['revenue'] += $learner->revenue;

        $eligible = $passed && (! $whole || $learner->partner !== null);
        foreach ($this->commissions($calc, $learner) as $header => [$amount, $color, $shown]) {
            $show = $eligible && $shown;
            $values[] = [$show ? $amount : '-', $show ? 'money:'.$color : 'right'];
            $totals['commissions'][$header] = ($totals['commissions'][$header] ?? 0.0) + ($show ? $amount : 0.0);
        }

        $order = null;
        foreach ($statement->book->registryIdsOf($learner->taxCode) as $registryId) {
            $order ??= $orders[$registryId] ?? null;
        }
        $values[] = [$order === null ? '-' : $order['id'].' - '.($order['date'] === null ? '' : date('d/m/Y', (int) strtotime($order['date']))), null];

        return $values;
    }

    /**
     * The learner's commission per applicable scenario: [amount, fill color, shown].
     *
     * @return array<string, array{0: float, 1: string, 2: bool}>
     */
    private function commissions(StatementCalculator $calc, StatementLearner $learner): array
    {
        $byPercentage = $learner->percentage > 0;
        $share = static fn (float $profitPerLearner, float $weight): float => $byPercentage
            ? ($profitPerLearner * $learner->percentage) / 100
            : $learner->amount * $weight;

        $forecast = $share($calc->marginPerLearner(), 1.0);
        $columns = ['€ Provv. Previsto' => [$forecast, StatementSheet::COLOR_FORECAST, $forecast > 0]];

        if ($calc->isAdvanceOnly() || $calc->isAdvanceAndBalance()) {
            $advance = $calc->tranche($calc->advance());
            $amount = $share($advance['profit_per_learner'], $advance['weight']);
            $columns['€ Provv. Acconto'] = [$amount, StatementSheet::COLOR_ADVANCE, $amount != 0];
        }

        if ($calc->isAdvanceOnly()) {
            $tranche = $calc->tranche($calc->forecastBalance());
            $amount = $share($tranche['profit_per_learner'], $tranche['weight']);
            $columns['€ Provv. Saldo (prev.)'] = [$amount, StatementSheet::COLOR_FORECAST_BALANCE, $amount != 0];
        } elseif ($calc->isBalanceOnly()) {
            $amount = $share($calc->actualMarginPerLearner(), 1.0);
            $columns['€ Provv. Saldo'] = [$amount, StatementSheet::COLOR_SINGLE_PAYMENT, $amount != 0];
        } elseif ($calc->isAdvanceAndBalance()) {
            $tranche = $calc->tranche($calc->balance());
            $amount = $share($tranche['profit_per_learner'], $tranche['weight']);
            $columns['€ Provv. Saldo'] = [$amount, StatementSheet::COLOR_BALANCE, $amount != 0];
        }

        return $columns;
    }

    /**
     * @param  list<array{0: mixed, 1: string|null}>  $values
     */
    private function writeRow(Worksheet $sheet, int $row, array $values, string $lastColumn, ?string $zebraUntil): void
    {
        foreach ($values as $index => [$value, $hint]) {
            $cell = $this->column($index).$row;
            $sheet->setCellValue($cell, $value);
            $this->applyHint($sheet, $cell, $hint);
        }

        $sheet->getStyle('D'.$row.':'.$lastColumn.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        if ($zebraUntil !== null) {
            StatementSheet::fill($sheet, 'D'.$row.':'.$zebraUntil.$row, StatementSheet::COLOR_ZEBRA);
        }
    }

    private function applyHint(Worksheet $sheet, string $cell, ?string $hint): void
    {
        if ($hint === null) {
            return;
        }

        [$kind, $color] = array_pad(explode(':', $hint, 2), 2, null);

        match (true) {
            $kind === 'money' => StatementSheet::money($sheet, $cell),
            $kind === 'center' => $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER),
            $kind === 'right' => $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT),
            default => StatementSheet::fill($sheet, $cell, $kind),
        };

        if ($kind === 'money' && $color !== null) {
            StatementSheet::fill($sheet, $cell, $color);
        }
        if (! in_array($kind, ['money', 'center', 'right'], true)) {
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, float>  $amounts  header => total
     */
    private function writeTotals(Worksheet $sheet, int $row, array $headers, string $lastColumn, array $amounts): void
    {
        $sheet->setCellValue('D'.$row, 'TOTALI:');
        $sheet->mergeCells('D'.$row.':'.$this->column(self::TOTALS_LABEL_SPAN).$row);
        $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        foreach ($amounts as $header => $amount) {
            $index = array_search($header, $headers, true);
            if ($index !== false) {
                $sheet->setCellValue($this->column($index).$row, $amount);
                $sheet->getStyle($this->column($index).$row)->getNumberFormat()->setFormatCode(StatementSheet::CURRENCY);
            }
        }

        $range = 'D'.$row.':'.$lastColumn.$row;
        $sheet->getStyle($range)->getFont()->setBold(true);
        StatementSheet::fill($sheet, $range, StatementSheet::COLOR_TOTALS_ROW);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /** Column letter of the n-th table column (0 = D). */
    private function column(int $index): string
    {
        return Coordinate::stringFromColumnIndex(self::FIRST_COLUMN + $index);
    }
}
