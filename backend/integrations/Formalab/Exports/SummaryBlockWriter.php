<?php

declare(strict_types=1);

namespace Integrations\Formalab\Exports;

use Integrations\Formalab\Services\LearnerStatement\LearnerStatement;
use Integrations\Formalab\Services\LearnerStatement\StatementCalculator;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Title rows and the "RIEPILOGO COSTI E RICAVI" column (A-B) of the legacy
 * export. Whole statement ($partner null): commissions are the totals, with
 * MOL Qualifica. Partner sheet: only that partner's commissions, no MOL.
 */
final class SummaryBlockWriter
{
    public function write(Worksheet $sheet, LearnerStatement $statement, ?string $partner, int $partnerLearners): int
    {
        // Step 1: title, class code and course line
        $this->writeTitle($sheet, $statement, $partner, $partnerLearners);

        // Step 2: costs
        $row = $this->writeCosts($sheet, $statement);

        // Step 3: the scenario blocks of the payment state
        $row = $this->writeScenarios($sheet, $statement->calculator, $partner, $row);

        $sheet->getStyle('A'.StatementSheet::START_ROW.':B'.($row - 1))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

        return $row;
    }

    private function writeTitle(Worksheet $sheet, LearnerStatement $statement, ?string $partner, int $partnerLearners): void
    {
        $sheet->setCellValue('A1', $partner === null ? 'PROSPETTO CONTABILITÀ DISCENTI' : 'PROSPETTO PROVVIGIONI — '.$partner);
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16]]);

        if ($statement->classCode() !== null) {
            $sheet->setCellValue('A2', 'Codice Classe: '.$statement->classCode());
            $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 11]]);
        }

        $info = $this->courseLine($statement);
        $learners = $statement->calculator->learners();
        $info .= $partner === null
            ? ' · Discenti: '.count($learners).' ('.count(array_filter($learners, static fn ($l): bool => $l->passed === true)).' superati, '
                .count(array_filter($learners, static fn ($l): bool => $l->passed === false)).' non superati)'
            : ' · Discenti del partner: '.$partnerLearners;
        $sheet->setCellValue('A3', $info);
        $sheet->getStyle('A3')->applyFromArray(['font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '666666']]]);
    }

    private function courseLine(LearnerStatement $statement): string
    {
        $title = trim((string) data_get($statement->class, 'corso.title', ''));
        $site = trim((string) data_get($statement->class, 'sede_parte_teorica.denominazione', ''));
        $city = trim((string) data_get($statement->class, 'sede_parte_teorica.city', ''));
        $line = $title !== '' ? 'Corso: '.$title : '';

        if ($site !== '') {
            $line .= ' · Sede: '.$site.($city !== '' ? ' ('.$city.')' : '');
        }

        return $line;
    }

    private function writeCosts(Worksheet $sheet, LearnerStatement $statement): int
    {
        $start = StatementSheet::START_ROW;
        $sheet->setCellValue('A'.$start, 'RIEPILOGO COSTI E RICAVI');
        $sheet->mergeCells('A'.$start.':B'.$start);
        $sheet->getStyle('A'.$start.':B'.$start)->applyFromArray(StatementSheet::titleStyle());

        $learnerCosts = $statement->quote->learnerCostsRounded();
        $total = $statement->calculator->totalCosts();
        $row = StatementSheet::amountRow($sheet, $start + 1, 'Totale Costo Discenti', $learnerCosts, true);

        foreach ($statement->quote->otherCostLines() as $line) {
            $label = $line['title'].($line['note'] !== '' ? ' ('.$line['note'].')' : '');
            $row = StatementSheet::amountRow($sheet, $row, $label, $line['amount'], false);
            $sheet->getStyle('A'.($row - 1))->getFont()->setColor(new Color(StatementSheet::COLOR_MUTED));
        }

        $row = StatementSheet::amountRow($sheet, $row, 'Totale Altri Costi', $total - $learnerCosts, true);

        return StatementSheet::amountRow($sheet, $row, 'TOTALE COSTI', $total, true, StatementSheet::COLOR_TOTAL_COSTS);
    }

    private function writeScenarios(Worksheet $sheet, StatementCalculator $calc, ?string $partner, int $row): int
    {
        $count = $calc->passedCount();
        $perLearner = 'Utile a discente ('.$count.')';
        $advance = $calc->tranche($calc->advance());
        $forecastBalance = $calc->tranche($calc->forecastBalance());
        $balance = $calc->tranche($calc->balance());
        $received = $partner === null ? '' : ' (ricevuto da Regione)';

        // Forecast ("previsto"), always
        $row = StatementSheet::scenarioHeader($sheet, $row, 'SCENARIO PREVISTO', '', StatementSheet::COLOR_FORECAST);
        $row = StatementSheet::amountRow($sheet, $row, 'Totale Ricavi Discenti', $calc->learnerRevenue(), false);
        $row = StatementSheet::amountRow($sheet, $row, 'Totale ricavi formazione', $calc->trainingRevenue(), false);
        $row = StatementSheet::amountRow($sheet, $row, 'TOTALE RICAVI', $calc->forecastRevenue(), true, StatementSheet::COLOR_FORECAST);
        $row = StatementSheet::amountRow($sheet, $row, 'Totale utile parziale', $calc->margin(), true);
        $row = $this->commissionRows($sheet, $row, $partner, '', $partner === null ? $calc->totalCommissions() : $this->partnerShare($calc, $partner, $calc->marginPerLearner(), 1.0), $calc->mol());
        $row = StatementSheet::amountRow($sheet, $row, $perLearner, $calc->marginPerLearner(), true);

        if ($calc->isAdvanceOnly() || $calc->isAdvanceAndBalance()) {
            $title = $calc->isAdvanceOnly() ? 'SCENARIO 1ª TRANCHE — ACCONTO REALE' : 'SCENARIO 1ª TRANCHE — ACCONTO';
            $row = StatementSheet::scenarioHeader($sheet, $row, $title, 'Peso tranche: '.StatementSheet::percent($advance['weight']), StatementSheet::COLOR_ADVANCE);
            $row = StatementSheet::amountRow($sheet, $row, 'Importo Anticipo'.$received, $advance['amount'], true);
            $row = $this->trancheRows($sheet, $row, $advance, $calc, $partner, ' (tranche)', 'MOL Qualifica tranche', $perLearner);
        }

        if ($calc->isAdvanceOnly()) {
            $row = StatementSheet::scenarioHeader($sheet, $row, 'SCENARIO 2ª TRANCHE — SALDO PREVISTO (previsionale)', 'Peso tranche: '.StatementSheet::percent($forecastBalance['weight']).' · stima saldo non ancora ricevuto', StatementSheet::COLOR_FORECAST_BALANCE);
            $row = StatementSheet::amountRow($sheet, $row, 'Importo Saldo (previsto = Previsto - Anticipo)', $forecastBalance['amount'], true);
            $row = $this->trancheRows($sheet, $row, $forecastBalance, $calc, $partner, ' (stima)', 'MOL Qualifica (stima)', $perLearner);

            $row = StatementSheet::scenarioHeader($sheet, $row, 'TOTALE ECONOMICO PREVISIONALE', 'Acconto reale + Saldo previsto', StatementSheet::COLOR_FORECAST_TOTAL);
            $row = StatementSheet::amountRow($sheet, $row, 'TOTALE RICAVI (Acconto + Saldo previsto)', $calc->forecastRevenue(), true);
            $row = StatementSheet::amountRow($sheet, $row, '- Totale Costi', $calc->totalCosts(), false, null, StatementSheet::COLOR_MUTED);
            $row = StatementSheet::amountRow($sheet, $row, 'Totale utile parziale', $calc->margin(), true);
            $partnerTotal = $partner === null ? 0.0 : $this->partnerTranche($calc, $partner, $advance) + $this->partnerTranche($calc, $partner, $forecastBalance);
            $row = $this->commissionRows($sheet, $row, $partner, ' (totali)', $partner === null ? $calc->totalCommissions() : $partnerTotal, $calc->mol());
            $row = StatementSheet::amountRow($sheet, $row, $perLearner, $calc->marginPerLearner(), true);
        }

        if ($calc->isAdvanceAndBalance()) {
            $row = StatementSheet::scenarioHeader($sheet, $row, 'SCENARIO FINANZIARIO 2ª TRANCHE — SALDO', 'Peso tranche: '.StatementSheet::percent($balance['weight']), StatementSheet::COLOR_BALANCE);
            $row = StatementSheet::amountRow($sheet, $row, 'Importo Saldo'.$received, $balance['amount'], true);
            $row = $this->trancheRows($sheet, $row, $balance, $calc, $partner, ' (tranche)', 'MOL Qualifica tranche', $perLearner);

            $row = StatementSheet::scenarioHeader($sheet, $row, 'TOTALE FINANZIARIO REALE', 'Acconto + Saldo', StatementSheet::COLOR_ACTUAL_TOTAL);
            $row = StatementSheet::amountRow($sheet, $row, 'TOTALE RICAVI (Acconto + Saldo)', $calc->receivedTotal(), true);
            $row = $this->differenceRow($sheet, $row, $calc->revenueDifference());
            $row = StatementSheet::amountRow($sheet, $row, '- Totale Costi', $calc->totalCosts(), false, null, StatementSheet::COLOR_MUTED);
            $row = StatementSheet::amountRow($sheet, $row, 'Totale utile parziale', $calc->actualMargin(), true);
            $partnerTotal = $partner === null ? 0.0 : $this->partnerTranche($calc, $partner, $advance) + $this->partnerTranche($calc, $partner, $balance);
            $row = $this->commissionRows($sheet, $row, $partner, ' (totali)', $partner === null ? $calc->totalCommissions() : $partnerTotal, $calc->actualMol());
            $row = StatementSheet::amountRow($sheet, $row, $perLearner, $calc->actualMarginPerLearner(), true);
        }

        if ($calc->isBalanceOnly()) {
            $row = StatementSheet::scenarioHeader($sheet, $row, 'SCENARIO REALE', 'Pagamento unico (saldo senza anticipo)', StatementSheet::COLOR_SINGLE_PAYMENT);
            $row = StatementSheet::amountRow($sheet, $row, 'Totale Ricavi Discenti', $calc->learnerRevenue(), false);
            $row = StatementSheet::amountRow($sheet, $row, 'Totale ricavi formazione', $calc->actualTrainingRevenue(), false);
            $row = StatementSheet::amountRow($sheet, $row, 'Importo Saldo'.$received, $calc->balance(), true);
            $row = StatementSheet::amountRow($sheet, $row, 'TOTALE RICAVI', $calc->receivedTotal(), true, StatementSheet::COLOR_SINGLE_PAYMENT);
            $row = $this->differenceRow($sheet, $row, $calc->revenueDifference());
            $row = StatementSheet::amountRow($sheet, $row, 'Totale utile parziale', $calc->actualMargin(), true);
            $row = $this->commissionRows($sheet, $row, $partner, '', $partner === null ? $calc->totalCommissions() : $this->partnerShare($calc, $partner, $calc->actualMarginPerLearner(), 1.0), $calc->actualMol());
            $row = StatementSheet::amountRow($sheet, $row, $perLearner, $calc->actualMarginPerLearner(), true);
        }

        return $row;
    }

    /**
     * @param  array<string, float>  $tranche
     */
    private function trancheRows(Worksheet $sheet, int $row, array $tranche, StatementCalculator $calc, ?string $partner, string $suffix, string $molLabel, string $perLearner): int
    {
        $row = StatementSheet::amountRow($sheet, $row, '- Costi tranche ('.StatementSheet::percent($tranche['weight']).')', $tranche['costs'], false, null, StatementSheet::COLOR_MUTED);
        $row = StatementSheet::amountRow($sheet, $row, 'Utile parziale tranche', $tranche['partial_profit'], true);
        $commissions = $partner === null ? $tranche['commissions'] : $this->partnerTranche($calc, $partner, $tranche);

        if ($commissions > 0) {
            $row = $partner === null
                ? StatementSheet::amountRow($sheet, StatementSheet::amountRow($sheet, $row, '- Provvigioni Partner'.$suffix, $commissions, false, null, StatementSheet::COLOR_MUTED), $molLabel, $tranche['mol'], true, StatementSheet::COLOR_MOL)
                : StatementSheet::amountRow($sheet, $row, 'Provvigioni '.$partner.$suffix, $commissions, true, StatementSheet::COLOR_COMMISSION_TOTAL);
        }

        return StatementSheet::amountRow($sheet, $row, $perLearner, $tranche['profit_per_learner'], true);
    }

    /** Whole statement: "- Provvigioni Partner" + MOL; partner sheet: that partner's line only. */
    private function commissionRows(Worksheet $sheet, int $row, ?string $partner, string $suffix, float $commissions, float $mol): int
    {
        if ($commissions <= 0) {
            return $row;
        }

        if ($partner !== null) {
            return StatementSheet::amountRow($sheet, $row, 'Provvigioni '.$partner.$suffix, $commissions, true, StatementSheet::COLOR_COMMISSION_TOTAL);
        }

        $row = StatementSheet::amountRow($sheet, $row, '- Provvigioni Partner'.$suffix, $commissions, false, null, StatementSheet::COLOR_MUTED);

        return StatementSheet::amountRow($sheet, $row, 'MOL Qualifica', $mol, true, StatementSheet::COLOR_MOL);
    }

    private function differenceRow(Worksheet $sheet, int $row, float $difference): int
    {
        if ($difference == 0) {
            return $row;
        }

        return StatementSheet::amountRow($sheet, $row, '   (Diff. vs previsto)', $difference, false, null, $difference >= 0 ? StatementSheet::COLOR_POSITIVE : StatementSheet::COLOR_NEGATIVE);
    }

    /**
     * One partner's commissions (`calcolaProvvigioniPartnerPerScenario`): a
     * fixed amount is weighted by the tranche, whole (1.0) outside tranches.
     */
    private function partnerShare(StatementCalculator $calc, string $partner, float $profitPerLearner, float $weight): float
    {
        return $calc->trancheCommissions($profitPerLearner, $weight, $partner);
    }

    /**
     * @param  array<string, float>  $tranche
     */
    private function partnerTranche(StatementCalculator $calc, string $partner, array $tranche): float
    {
        return $this->partnerShare($calc, $partner, $tranche['profit_per_learner'], $tranche['weight']);
    }
}
