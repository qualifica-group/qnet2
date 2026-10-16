<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Agents;

use App\DataObjects\ManagementControl\AgentsFilters;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use Illuminate\Support\Facades\DB;

/**
 * The agents report (spec 0272, D-5): per agent, what accrued in the period,
 * what is still to settle, what sits in a statement, what is approved and
 * unpaid, and what was paid in the period. The top agents by amount to settle
 * are listed one by one, the rest is summed in "others"; the totals are the
 * sum of both. A constant number of aggregate queries; amounts are integer
 * cents and leave as numbers with two decimals.
 */
final class AgentsBuilder
{
    private const array FIGURES = ['accrued', 'to_settle', 'in_statement', 'approved_unpaid', 'paid'];

    public function __construct(
        private readonly AgentAccrualAggregator $accruals,
        private readonly AgentSettlementAggregator $settlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(AgentsFilters $filters): array
    {
        // Step 1: the two ledgers, by agent and month
        $ledger = $this->accruals->handle($filters);
        $statements = $this->settlements->handle($filters);

        // Step 2: the figures of each agent, the ones with nothing to show dropped
        $agents = $this->agents($ledger, $statements);

        // Step 3: top agents by amount to settle, the rest summed
        $names = $this->names(array_keys($agents));
        $ranked = $this->ranked($agents, $names);
        $top = array_slice($ranked, 0, $filters->top);
        $others = array_slice($ranked, $filters->top);

        $total = $this->sum($agents);

        return [
            'totals' => [...$this->wire($total), 'agents_count' => count($agents), 'pending_document_count' => $total['pending_document_count']],
            'agents' => array_map(fn (int $id): array => [
                'referent' => ['id' => $id, 'name' => $names[$id] ?? (string) $id],
                'pending_document_count' => $agents[$id]['pending_document_count'],
                ...$this->wire($agents[$id]),
            ], $top),
            'others' => $others === [] ? null : $this->othersRow(array_intersect_key($agents, array_flip($others))),
            // Step 4: the months of the period
            'by_month' => $this->byMonth($filters, $ledger['accrued_by_month'], $statements['paid_by_month']),
        ];
    }

    /**
     * Cents by figure by referent id, plus the number of statements waiting for the document.
     *
     * @param  array<string, array<mixed>>  $ledger
     * @param  array<string, array<mixed>>  $statements
     * @return array<int, array<string, int>>
     */
    private function agents(array $ledger, array $statements): array
    {
        $ids = array_unique([
            ...array_keys($ledger['accrued']), ...array_keys($ledger['to_settle']),
            ...array_keys($statements['in_statement']), ...array_keys($statements['approved_net']),
            ...array_keys($statements['paid']), ...array_keys($statements['pending_document']),
        ]);

        $agents = [];
        foreach ($ids as $id) {
            $agent = [
                'accrued' => $ledger['accrued'][$id] ?? 0,
                'to_settle' => $ledger['to_settle'][$id] ?? 0,
                'in_statement' => $statements['in_statement'][$id] ?? 0,
                'approved_unpaid' => ($statements['approved_net'][$id] ?? 0) - ($statements['approved_paid'][$id] ?? 0),
                'paid' => $statements['paid'][$id] ?? 0,
                'pending_document_count' => $statements['pending_document'][$id] ?? 0,
            ];

            if (array_sum(array_map('abs', $agent)) !== 0) {
                $agents[(int) $id] = $agent;
            }
        }

        return $agents;
    }

    /**
     * Agent ids by amount to settle, then accrued, descending; the name and the id settle ties.
     *
     * @param  array<int, array<string, int>>  $agents
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function ranked(array $agents, array $names): array
    {
        $ids = array_keys($agents);
        usort($ids, fn (int $a, int $b): int => ($agents[$b]['to_settle'] <=> $agents[$a]['to_settle'])
            ?: ($agents[$b]['accrued'] <=> $agents[$a]['accrued'])
            ?: strcasecmp($names[$a] ?? '', $names[$b] ?? '')
            ?: $a <=> $b);

        return $ids;
    }

    /**
     * @param  array<int, array<string, int>>  $agents
     * @return array<string, int|float>
     */
    private function othersRow(array $agents): array
    {
        $total = $this->sum($agents);

        return [...$this->wire($total), 'agents_count' => count($agents), 'pending_document_count' => $total['pending_document_count']];
    }

    /**
     * @param  array<int, array<string, int>>  $agents
     * @return array<string, int>
     */
    private function sum(array $agents): array
    {
        $total = array_fill_keys([...self::FIGURES, 'pending_document_count'], 0);
        foreach ($agents as $agent) {
            foreach ($total as $figure => $value) {
                $total[$figure] = $value + $agent[$figure];
            }
        }

        return $total;
    }

    /**
     * @param  array<string, int>  $figures
     * @return array<string, float>
     */
    private function wire(array $figures): array
    {
        return array_combine(self::FIGURES, array_map(fn (string $figure): float => ExposureSupport::euros($figures[$figure]), self::FIGURES));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('referents')->whereIn('id', $ids)->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])->all();
    }

    /**
     * Every month of the period; an open side of the period is bounded by the first or last month with data.
     *
     * @param  array<string, int>  $accrued  cents by month
     * @param  array<string, int>  $paid  cents by month
     * @return array<int, array{month: string, accrued: float, paid: float}>
     */
    private function byMonth(AgentsFilters $filters, array $accrued, array $paid): array
    {
        $dataMonths = array_keys($accrued + $paid);
        sort($dataMonths);

        $from = $filters->dateFrom ?? (isset($dataMonths[0]) ? $dataMonths[0].'-01' : null);
        $to = $filters->dateTo ?? ($dataMonths === [] ? null : end($dataMonths).'-01');
        if ($from === null || $to === null) {
            return [];
        }

        return array_map(fn (string $month): array => [
            'month' => $month,
            'accrued' => ExposureSupport::euros($accrued[$month] ?? 0),
            'paid' => ExposureSupport::euros($paid[$month] ?? 0),
        ], AgentsFilters::monthsBetween($from, $to));
    }
}
