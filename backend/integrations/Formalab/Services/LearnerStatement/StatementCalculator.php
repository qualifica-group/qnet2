<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

/**
 * Every figure of the legacy prospetto (spec 0427 D-1), method by method:
 * the forecast ("economico") from the quote and the learners, the actual
 * ("finanziario") from the advance and balance collected, the tranche
 * weights, and the partner commissions on the profit per learner of the
 * chosen scenario. Plain PHP floats, rounded where the legacy rounds.
 *
 * Payment states: A advance only (advance + forecast balance), B balance only
 * (single payment), C both (two actual tranches).
 */
final class StatementCalculator
{
    public const string ECONOMIC = 'economic';

    public const string FINANCIAL = 'financial';

    private readonly string $scenario;

    /**
     * @param  list<StatementLearner>  $learners  in roster order
     */
    public function __construct(
        private readonly array $learners,
        private readonly float $learnerCosts,
        private readonly float $totalCosts,
        private readonly float $trainingRevenue,
        private readonly float $advance,
        private readonly float $balance,
        ?string $scenario,
    ) {
        // Default financial once the balance is in; financial without a balance falls back to economic.
        $wanted = $scenario ?? ($this->financialAvailable() ? self::FINANCIAL : self::ECONOMIC);
        $this->scenario = $wanted === self::FINANCIAL && $this->financialAvailable() ? self::FINANCIAL : self::ECONOMIC;
    }

    /**
     * @return list<StatementLearner>
     */
    public function learners(): array
    {
        return $this->learners;
    }

    public function scenario(): string
    {
        return $this->scenario;
    }

    public function financialAvailable(): bool
    {
        return $this->balance > 0;
    }

    public function advance(): float
    {
        return $this->advance;
    }

    public function balance(): float
    {
        return $this->balance;
    }

    public function learnerCosts(): float
    {
        return $this->learnerCosts;
    }

    public function totalCosts(): float
    {
        return $this->totalCosts;
    }

    public function otherCosts(): float
    {
        return $this->totalCosts - $this->learnerCosts;
    }

    public function trainingRevenue(): float
    {
        return $this->trainingRevenue;
    }

    public function learnerRevenue(): float
    {
        return array_sum(array_map(static fn (StatementLearner $l): float => $l->revenue, $this->learners));
    }

    /** TOTALE RICAVI previsto (`getTotaleRicaviComplessivo`). */
    public function forecastRevenue(): float
    {
        return $this->learnerRevenue() + $this->trainingRevenue;
    }

    public function receivedTotal(): float
    {
        return $this->advance + $this->balance;
    }

    /** Totale ricavi formazione reale: what the region paid minus the learner revenue. */
    public function actualTrainingRevenue(): float
    {
        return $this->receivedTotal() - $this->learnerRevenue();
    }

    /** Totale utile parziale previsto (`getMargine`). */
    public function margin(): float
    {
        return ($this->trainingRevenue + $this->learnerRevenue()) - $this->totalCosts;
    }

    public function actualMargin(): float
    {
        return $this->receivedTotal() - $this->totalCosts;
    }

    public function revenueDifference(): float
    {
        return $this->receivedTotal() - $this->forecastRevenue();
    }

    /** Learners who passed the course: the divisor of every "per learner" figure. */
    public function passedCount(): int
    {
        return count(array_filter($this->learners, static fn (StatementLearner $l): bool => $l->passed === true));
    }

    public function marginPerLearner(): float
    {
        return $this->perLearner($this->margin());
    }

    public function actualMarginPerLearner(): float
    {
        return $this->perLearner($this->actualMargin());
    }

    public function scenarioProfitPerLearner(): float
    {
        return $this->scenario === self::FINANCIAL ? $this->actualMarginPerLearner() : $this->marginPerLearner();
    }

    public function scenarioPartialProfit(): float
    {
        return $this->scenario === self::FINANCIAL ? $this->actualMargin() : $this->margin();
    }

    /** One learner's commission: percentage of the scenario profit per learner, else the fixed amount. */
    public function commissionOf(StatementLearner $learner): float
    {
        if ($learner->percentage > 0) {
            return round(($this->scenarioProfitPerLearner() * $learner->percentage) / 100, 2);
        }

        return $learner->amount;
    }

    /**
     * Commissions grouped by partner, sorted by name (`riepilogoProvvigioniPartner`):
     * every learner with a partner except those who FAILED (an unknown outcome counts).
     *
     * @return list<array{partner: string, learners_count: int, total: float, learners: list<array{name: string, percentage: float, manual_amount: float, commission: float, type: string}>}>
     */
    public function partnerSummary(): array
    {
        $summary = [];

        foreach ($this->learners as $learner) {
            if ($learner->passed === false || $learner->partner === null) {
                continue;
            }

            $commission = $this->commissionOf($learner);
            $summary[$learner->partner] ??= ['partner' => $learner->partner, 'learners_count' => 0, 'total' => 0.0, 'learners' => []];
            $summary[$learner->partner]['learners_count']++;
            $summary[$learner->partner]['total'] += $commission;
            $summary[$learner->partner]['learners'][] = [
                'name' => $learner->fullName(),
                'percentage' => $learner->percentage,
                'manual_amount' => $learner->amount,
                'commission' => $commission,
                'type' => $learner->percentage > 0 ? 'percentage' : 'amount',
            ];
        }

        uasort($summary, static fn (array $a, array $b): int => $a['partner'] <=> $b['partner']);

        return array_values($summary);
    }

    public function totalCommissions(): float
    {
        return array_sum(array_column($this->partnerSummary(), 'total'));
    }

    /** MOL Qualifica = forecast partial profit - commissions. */
    public function mol(): float
    {
        return $this->margin() - $this->totalCommissions();
    }

    public function actualMol(): float
    {
        return $this->actualMargin() - $this->totalCommissions();
    }

    public function isAdvanceOnly(): bool
    {
        return $this->advance > 0 && $this->balance <= 0;
    }

    public function isBalanceOnly(): bool
    {
        return $this->advance <= 0 && $this->balance > 0;
    }

    public function isAdvanceAndBalance(): bool
    {
        return $this->advance > 0 && $this->balance > 0;
    }

    /** State A: forecast total (advance + forecast balance = 100%); state C: actual total. */
    public function trancheDenominator(): float
    {
        if ($this->isAdvanceAndBalance()) {
            return $this->advance + $this->balance;
        }
        if ($this->isAdvanceOnly()) {
            return $this->forecastRevenue();
        }

        return 0.0;
    }

    public function trancheWeight(float $amount): float
    {
        $denominator = $this->trancheDenominator();

        return $denominator <= 0 ? 0.0 : $amount / $denominator;
    }

    /** Saldo previsto = forecast total - advance (state A). */
    public function forecastBalance(): float
    {
        return $this->forecastRevenue() - $this->advance;
    }

    /**
     * One tranche of the given amount: its share of the costs, profit,
     * commissions and MOL.
     *
     * @return array{amount: float, weight: float, costs: float, partial_profit: float, commissions: float, mol: float, profit_per_learner: float}
     */
    public function tranche(float $amount): array
    {
        $weight = $this->trancheWeight($amount);
        $costs = $this->totalCosts * $weight;
        $profit = $amount - $costs;
        $perLearner = $this->perLearner($profit);
        $commissions = $this->trancheCommissions($perLearner, $weight);

        return [
            'amount' => $amount,
            'weight' => $weight,
            'costs' => $costs,
            'partial_profit' => $profit,
            'commissions' => $commissions,
            'mol' => $profit - $commissions,
            'profit_per_learner' => $perLearner,
        ];
    }

    /**
     * Commissions of a tranche (`calcolaProvvigioniTranche`): only learners who
     * PASSED; a percentage applies to the tranche profit per learner, a fixed
     * amount is weighted by the tranche.
     */
    public function trancheCommissions(float $profitPerLearner, float $weight, ?string $partner = null): float
    {
        $total = 0.0;

        foreach ($this->learners as $learner) {
            if ($learner->passed !== true || ($partner !== null && $learner->partner !== $partner)) {
                continue;
            }
            $total += $learner->percentage > 0
                ? ($profitPerLearner * $learner->percentage) / 100
                : $learner->amount * $weight;
        }

        return $total;
    }

    /**
     * Commissions of one partner's learners on a tranche, as the legacy tranche
     * tables of the Excel export sum them (from the partner summary rows).
     */
    public function partnerTrancheCommissions(array $partnerSummaryRow, float $profitPerLearner, float $weight): float
    {
        $total = 0.0;

        foreach ($partnerSummaryRow['learners'] as $detail) {
            $total += $detail['type'] === 'percentage'
                ? ($profitPerLearner * $detail['percentage']) / 100
                : $detail['manual_amount'] * $weight;
        }

        return $total;
    }

    private function perLearner(float $value): float
    {
        $count = $this->passedCount();

        return $count === 0 ? 0.0 : $value / $count;
    }
}
