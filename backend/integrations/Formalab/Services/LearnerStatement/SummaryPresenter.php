<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

/**
 * The left column of the prospetto ("Riepilogo Costi e Ricavi"): costs and
 * the scenario blocks the payment state shows (spec 0427 AC-005).
 */
final class SummaryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function costs(LearnerStatement $statement): array
    {
        $calc = $statement->calculator;

        return [
            'learners' => $calc->learnerCosts(),
            'other_lines' => $statement->quote->otherCostLines(),
            'other_total' => $calc->otherCosts(),
            'total' => $calc->totalCosts(),
        ];
    }

    public function paymentState(StatementCalculator $calc): string
    {
        return match (true) {
            $calc->isAdvanceAndBalance() => 'advance_and_balance',
            $calc->isAdvanceOnly() => 'advance_only',
            $calc->isBalanceOnly() => 'balance_only',
            default => 'none',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(StatementCalculator $calc): array
    {
        return [
            'learners_count' => $calc->passedCount(),
            'learner_revenue' => $calc->learnerRevenue(),
            'training_revenue' => $calc->trainingRevenue(),
            'total_revenue' => $calc->forecastRevenue(),
            'partial_profit' => $calc->margin(),
            'commissions' => $calc->totalCommissions(),
            'mol' => $calc->mol(),
            'profit_per_learner' => $calc->marginPerLearner(),
            // Blocks by payment state: A advance + forecast balance, C advance + balance, B single payment.
            'advance' => $calc->isAdvanceOnly() || $calc->isAdvanceAndBalance() ? $calc->tranche($calc->advance()) : null,
            'forecast_balance' => $calc->isAdvanceOnly() ? $calc->tranche($calc->forecastBalance()) : null,
            'balance' => $calc->isAdvanceAndBalance() ? $calc->tranche($calc->balance()) : null,
            'forecast_total' => $calc->isAdvanceOnly() ? $this->forecastTotal($calc) : null,
            'actual_total' => $calc->isAdvanceAndBalance() ? $this->actualTotal($calc) : null,
            'single_payment' => $calc->isBalanceOnly() ? $this->singlePayment($calc) : null,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function forecastTotal(StatementCalculator $calc): array
    {
        return [
            'total_revenue' => $calc->forecastRevenue(),
            'total_costs' => $calc->totalCosts(),
            'partial_profit' => $calc->margin(),
            'commissions' => $calc->totalCommissions(),
            'mol' => $calc->mol(),
            'profit_per_learner' => $calc->marginPerLearner(),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function actualTotal(StatementCalculator $calc): array
    {
        return [
            'total_revenue' => $calc->receivedTotal(),
            'difference' => $calc->revenueDifference(),
            'total_costs' => $calc->totalCosts(),
            'partial_profit' => $calc->actualMargin(),
            'commissions' => $calc->totalCommissions(),
            'mol' => $calc->actualMol(),
            'profit_per_learner' => $calc->actualMarginPerLearner(),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function singlePayment(StatementCalculator $calc): array
    {
        return [
            'learner_revenue' => $calc->learnerRevenue(),
            'training_revenue' => $calc->actualTrainingRevenue(),
            'balance' => $calc->balance(),
            'total_revenue' => $calc->receivedTotal(),
            'difference' => $calc->revenueDifference(),
            'partial_profit' => $calc->actualMargin(),
            'commissions' => $calc->totalCommissions(),
            'mol' => $calc->actualMol(),
            'profit_per_learner' => $calc->actualMarginPerLearner(),
        ];
    }
}
