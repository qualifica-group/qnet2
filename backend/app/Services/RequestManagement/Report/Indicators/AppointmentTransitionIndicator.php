<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\IndicatorResult;
use App\Services\RequestManagement\Report\QuoteCountAggregator;
use App\Services\RequestManagement\Report\ReportBranchQuery;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicator;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * "Presa Appuntamenti" (spec 0170 D-3/D-4): requests with a logged status
 * change, inside the range, that went DIRECTLY from an "OK App. Fissato..."
 * status to the "Assegnato" status of the same workflow — once it happened it
 * counts, whatever the status is today. The two names come from
 * `config('request-management-report.appointment_transition')`; a workflow
 * lacking either status yields no pair, so its requests count 0.
 */
final class AppointmentTransitionIndicator implements ReportIndicator
{
    use QueriesLoggedStatusTransitions;

    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null, RequestModule $module = RequestModule::Requests): IndicatorResult
    {
        $pairs = $this->statusPairs();

        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators, $sites, $pairs, $module), 'distinct quotes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators, $sites, $pairs, $module), 'distinct quotes.id'),
        );
    }

    /**
     * Every [from, to] status id pair sharing the same workflow (the global
     * default set included), names compared trimmed and case-insensitively.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function statusPairs(): array
    {
        $fromPrefix = $this->normalized((string) config('request-management-report.appointment_transition.from_status_prefix'));
        $toName = $this->normalized((string) config('request-management-report.appointment_transition.to_status'));

        if ($fromPrefix === '' || $toName === '') {
            return [];
        }

        $statuses = QuoteWorkflowStatus::query()->get(['id', 'name', 'quote_workflow_id']);
        $fromByWorkflow = $this->byWorkflow($statuses->filter(fn (QuoteWorkflowStatus $status): bool => str_starts_with($this->normalized($status->name), $fromPrefix)));
        $toByWorkflow = $this->byWorkflow($statuses->filter(fn (QuoteWorkflowStatus $status): bool => $this->normalized($status->name) === $toName));

        $pairs = [];
        foreach ($fromByWorkflow as $workflowKey => $fromIds) {
            foreach ($fromIds as $fromId) {
                foreach ($toByWorkflow[$workflowKey] ?? [] as $toId) {
                    $pairs[] = [$fromId, $toId];
                }
            }
        }

        return $pairs;
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     * @return array<string, array<int, int>>
     */
    private function byWorkflow(Collection $statuses): array
    {
        return $statuses
            ->groupBy(static fn (QuoteWorkflowStatus $status): string => (string) $status->quote_workflow_id)
            ->map(static fn (Collection $group): array => $group->pluck('id')->all())
            ->all();
    }

    private function normalized(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, array{0: int, 1: int}>  $pairs
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, array $pairs, RequestModule $module): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereExists(function (QueryBuilder $sub) use ($pairs, $range): void {
                $this->loggedTransitionOnCurrentWorkflow($sub, $range)
                    ->where(function (QueryBuilder $anyPair) use ($pairs): void {
                        // No configured pair: `whereIn(..., [])` matches nothing, so the column is 0.
                        if ($pairs === []) {
                            $anyPair->whereIn(self::LOGGED_STATUS_COLUMN, []);

                            return;
                        }

                        foreach ($pairs as [$fromId, $toId]) {
                            $anyPair->orWhere(function (QueryBuilder $pair) use ($fromId, $toId): void {
                                $pair->where(self::LOGGED_PREVIOUS_STATUS_COLUMN, $fromId)
                                    ->where(self::LOGGED_STATUS_COLUMN, $toId);
                            });
                        }
                    });
            });
    }
}
