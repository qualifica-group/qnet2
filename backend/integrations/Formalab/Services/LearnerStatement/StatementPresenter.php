<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Support\Str;
use Integrations\Formalab\Services\ClassPayloadMapper;

/**
 * The prospetto as the frozen JSON contract of spec 0427: course info,
 * summary, learners, partner commissions and the monthly progress.
 */
final class StatementPresenter
{
    private const int MINUTES_PER_HOUR = 60;

    private const array PHASES = ['presence_training' => ['presenza', 'formazione'], 'presence_internship' => ['presenza', 'tirocinio'], 'absence_training' => ['assenza', 'formazione'], 'absence_internship' => ['assenza', 'tirocinio']];

    public function __construct(
        private readonly SummaryPresenter $summary,
        private readonly ClassPayloadMapper $mapper,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(LearnerStatement $statement, bool $canManage): array
    {
        $calc = $statement->calculator;

        return [
            'edition_id' => $statement->editionId,
            'class_status' => $statement->classStatus(),
            'can_manage' => $canManage,
            'class' => $this->classInfo($statement),
            'rates' => ['cost_per_hour' => $statement->rates->costPerHour(), 'revenue_per_hour' => $statement->rates->revenuePerHour()],
            'commission_scenario' => $calc->scenario(),
            'financial_available' => $calc->financialAvailable(),
            'payment_state' => $this->summary->paymentState($calc),
            'costs' => $this->summary->costs($statement),
            'summary' => $this->summary->summary($calc),
            'learners' => $this->learners($statement),
            'totals' => $this->totals($statement),
            'partners' => $this->partners($statement),
            'progress' => $this->progress($statement),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function classInfo(LearnerStatement $statement): ?array
    {
        $mapped = $statement->class === null ? null : $this->mapper->map($statement->class);

        if ($mapped === null) {
            return null;
        }

        $learners = $statement->calculator->learners();
        $total = count($learners);
        $passed = count(array_filter($learners, static fn (StatementLearner $l): bool => $l->passed === true));

        return [
            'code' => $statement->classCode(),
            'title' => $mapped['title'],
            'status' => $mapped['status'],
            'start_date' => $mapped['start_date'],
            'end_date' => $mapped['end_date'],
            'total_hours' => $mapped['total_hours'],
            'two_phase' => $statement->twoPhase(),
            'site_name' => $mapped['site_name'],
            'site_address' => data_get($statement->class, 'sede_parte_teorica.address'),
            'site_city' => $mapped['site_city'],
            'site_code' => $mapped['site_code'],
            'learners_total' => $total,
            'learners_passed' => $passed,
            'learners_failed' => count(array_filter($learners, static fn (StatementLearner $l): bool => $l->passed === false)),
            'passed_percentage' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function learners(LearnerStatement $statement): array
    {
        $calc = $statement->calculator;
        $orders = $statement->quote->learnerOrdersBySupplier();
        $twoPhase = $statement->twoPhase();

        return array_map(function (StatementLearner $learner) use ($calc, $orders, $statement, $twoPhase): array {
            $registryWithOrder = array_values(array_filter($statement->book->registryIdsOf($learner->taxCode), static fn (int $id): bool => isset($orders[$id])));

            return [
                'id' => $learner->payload['id'] ?? null,
                'first_name' => $learner->firstName(),
                'last_name' => $learner->lastName(),
                'tax_code' => $learner->taxCode,
                'presence' => ['hours' => $learner->int('ore_presenza'), 'minutes' => $learner->int('minuti_presenza')],
                'absence' => ['hours' => $learner->int('ore_assenza'), 'minutes' => $learner->int('minuti_assenza')],
                'phases' => $twoPhase ? $this->phaseMinutes([$learner]) : null,
                'passed' => $learner->passed,
                'cost_hours' => $learner->costHours,
                'cost' => $learner->cost,
                'revenue_hours' => $learner->revenueHours,
                'revenue' => $learner->revenue,
                'margin' => $learner->margin,
                'partner' => $learner->partner,
                'percentage' => $learner->percentage,
                'amount' => $learner->amount,
                'commission' => $learner->partner !== null ? $calc->commissionOf($learner) : 0.0,
                'learner_order' => $registryWithOrder === [] ? null : $orders[$registryWithOrder[0]],
            ];
        }, $calc->learners());
    }

    /**
     * Footer totals (`totaleOrePresenza`, `totaliOreDueFasi`).
     *
     * @return array<string, mixed>
     */
    private function totals(LearnerStatement $statement): array
    {
        $learners = $statement->calculator->learners();

        return [
            'presence' => $this->hoursTotal($learners, 'ore_presenza', 'minuti_presenza'),
            'absence' => $this->hoursTotal($learners, 'ore_assenza', 'minuti_assenza'),
            'phases' => $statement->twoPhase() ? $this->phaseMinutes($learners) : null,
            'learner_costs' => $statement->calculator->learnerCosts(),
            'learner_revenue' => $statement->calculator->learnerRevenue(),
        ];
    }

    /**
     * Hours summed apart, the minutes carried over in whole hours.
     *
     * @param  list<StatementLearner>  $learners
     * @return array{hours: int, minutes: int}
     */
    private function hoursTotal(array $learners, string $hoursKey, string $minutesKey): array
    {
        $hours = array_sum(array_map(static fn (StatementLearner $l): int => $l->int($hoursKey), $learners));
        $minutes = array_sum(array_map(static fn (StatementLearner $l): int => $l->int($minutesKey), $learners));

        return ['hours' => intdiv($minutes, self::MINUTES_PER_HOUR) + $hours, 'minutes' => $minutes % self::MINUTES_PER_HOUR];
    }

    /**
     * Minutes per phase of the two-phase courses (`minutiFase`), summed.
     *
     * @param  list<StatementLearner>  $learners
     * @return array<string, int>
     */
    private function phaseMinutes(array $learners): array
    {
        $totals = [];

        foreach (self::PHASES as $key => [$type, $phase]) {
            $totals[$key] = array_sum(array_map(static fn (StatementLearner $l): int => (int) round(
                (float) ($l->payload["ore_{$type}_{$phase}"] ?? 0) * self::MINUTES_PER_HOUR + (float) ($l->payload["minuti_{$type}_{$phase}"] ?? 0)
            ), $learners));
        }

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    private function partners(LearnerStatement $statement): array
    {
        $calc = $statement->calculator;
        $suppliers = $statement->partnerSuppliers();
        $names = Registry::query()->whereIn('id', array_values($suppliers))->pluck('name', 'id');
        $orders = PurchaseOrder::query()
            ->where('work_order_id', $statement->workOrder->id)
            ->whereIn('supplier_id', array_values($suppliers))
            ->orderByDesc('id')
            ->get(['id', 'number', 'year', 'status', 'grand_total', 'supplier_id'])
            ->unique('supplier_id')
            ->keyBy('supplier_id');

        $items = array_map(function (array $row) use ($suppliers, $names, $orders): array {
            $supplierId = $suppliers[$row['partner']] ?? null;
            $order = $supplierId === null ? null : $orders->get($supplierId);

            return [
                'key' => Str::slug($row['partner'], '_'),
                'name' => $row['partner'],
                'learners_count' => $row['learners_count'],
                'total' => $row['total'],
                'supplier' => $supplierId === null ? null : ['id' => $supplierId, 'name' => $names->get($supplierId)],
                'purchase_order' => $order === null ? null : ['id' => $order->id, 'number' => $order->code(), 'status' => $order->status->value, 'total' => (float) $order->grand_total],
                'learners' => $row['learners'],
            ];
        }, $calc->partnerSummary());

        $forecastBalance = $calc->forecastBalance();

        return [
            'partial_profit' => $calc->scenarioPartialProfit(),
            'profit_per_learner' => $calc->scenarioProfitPerLearner(),
            'total' => $calc->totalCommissions(),
            'tranche' => [
                'advance_weight' => $calc->trancheWeight($calc->advance()),
                'advance_commissions' => $calc->tranche($calc->advance())['commissions'],
                'balance_weight' => $calc->trancheWeight($calc->balance()),
                'balance_commissions' => $calc->tranche($calc->balance())['commissions'],
                'forecast_balance_weight' => $calc->trancheWeight($forecastBalance),
                'forecast_balance_commissions' => $calc->tranche($forecastBalance)['commissions'],
            ],
            'items' => $items,
        ];
    }

    /**
     * "Avanzamento Commessa": progress entries summed by month, the learner
     * service revenue spread by those percentages.
     *
     * @return array<string, mixed>
     */
    private function progress(LearnerStatement $statement): array
    {
        $serviceRevenue = $statement->quote->learnerServiceRevenue();
        $months = WorkOrderProgressEntry::query()
            ->where('work_order_id', $statement->workOrder->id)
            ->get(['percentage', 'recorded_on'])
            ->groupBy(static fn (WorkOrderProgressEntry $entry): string => $entry->recorded_on->format('Y-m'))
            ->sortKeys();

        $items = $months->map(static function ($entries, string $month) use ($serviceRevenue): array {
            $percentage = (float) $entries->sum('percentage');
            [$year, $number] = explode('-', $month);

            return ['year' => (int) $year, 'month' => (int) $number, 'percentage' => $percentage, 'revenue' => ($serviceRevenue * $percentage) / 100];
        })->values()->all();

        return [
            'items' => $items,
            'total_percentage' => array_sum(array_column($items, 'percentage')),
            'total_revenue' => array_sum(array_column($items, 'revenue')),
        ];
    }
}
