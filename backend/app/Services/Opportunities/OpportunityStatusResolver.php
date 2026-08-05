<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Support\Collection;

/**
 * The Opportunity's status, COMPUTED (spec 0082): an Opportunity no longer
 * carries a hand-picked status FK — its state is read off the statuses of its
 * Quotes, falling back to its own "stato di lavorazione"
 * (`opportunity_workflow_status_id`, spec 0047) when it has no Quote yet.
 *
 * The single source of truth for BR-1..BR-4: every consumer (OpportunityResource,
 * OpportunitiesTableDefinition, RequestManagementResource, RewardResource,
 * notifications) projects the shape produced here, never its own aggregation.
 *
 * Callers on a collection MUST eager-load self::EAGER_LOADS — the in-memory
 * path is the only one that stays O(1) in queries across a table page. On a
 * bare, route-bound model the resolver falls back to ONE explicit aggregate
 * query (never a lazy relation access, so Model::preventLazyLoading() stays
 * satisfied).
 */
final class OpportunityStatusResolver
{
    /** At least one Quote: the status is described by the quotes' own statuses. */
    public const string SOURCE_QUOTES = 'quotes';

    /** No Quote at all: the status falls back to the "stato di lavorazione". */
    public const string SOURCE_WORKFLOW = 'workflow';

    /**
     * The relations resolve() reads. Eager-load these whenever the resolver
     * runs over more than one Opportunity.
     *
     * @var array<int, string>
     */
    public const array EAGER_LOADS = ['quotes.quoteStatus', 'workflowStatus'];

    /**
     * @return array{source: string, distinct_count: int, entries: array<int, array{id: int, name: string, color: string|null, group: string, count: int}>}
     */
    public function resolve(Opportunity $opportunity): array
    {
        // Step 1: the quotes' statuses win whenever the opportunity has any.
        $entries = $this->quoteEntries($opportunity);

        if ($entries !== []) {
            return $this->summary(self::SOURCE_QUOTES, $entries);
        }

        // Step 2: no quote — fall back to the opportunity's own working state.
        return $this->summary(self::SOURCE_WORKFLOW, $this->workflowEntries($opportunity));
    }

    /**
     * @param  array<int, array{id: int, name: string, color: string|null, group: string, count: int}>  $entries
     * @return array{source: string, distinct_count: int, entries: array<int, array{id: int, name: string, color: string|null, group: string, count: int}>}
     */
    private function summary(string $source, array $entries): array
    {
        return [
            'source' => $source,
            'distinct_count' => count($entries),
            'entries' => $entries,
        ];
    }

    /**
     * One entry per DISTINCT quote status (BR-1), carrying how many quotes sit
     * in it, ordered by the status' own `sort_order`.
     *
     * @return array<int, array{id: int, name: string, color: string|null, group: string, count: int}>
     */
    private function quoteEntries(Opportunity $opportunity): array
    {
        $quotes = $opportunity->relationLoaded('quotes')
            ? $opportunity->quotes
            : $this->fetchQuotes($opportunity);

        return $quotes
            ->filter(static fn (Quote $quote): bool => $quote->quoteStatus !== null)
            ->groupBy(static fn (Quote $quote): int => (int) $quote->quote_status_id)
            ->map(static function (Collection $group): array {
                $status = $group->first()->quoteStatus;

                return [
                    'id' => (int) $status->id,
                    'name' => (string) $status->name,
                    'color' => $status->color,
                    'group' => $status->group->value,
                    'count' => $group->count(),
                    'sort_order' => (int) $status->sort_order,
                ];
            })
            ->sortBy('sort_order')
            ->map(static function (array $entry): array {
                unset($entry['sort_order']);

                return $entry;
            })
            ->values()
            ->all();
    }

    /**
     * The fallback entry (BR-4): the resolved "stato di lavorazione", or an
     * empty list when none was ever resolved (the FK is nullable).
     *
     * @return array<int, array{id: int, name: string, color: string|null, group: string, count: int}>
     */
    private function workflowEntries(Opportunity $opportunity): array
    {
        $status = $opportunity->relationLoaded('workflowStatus')
            ? $opportunity->workflowStatus
            : $opportunity->workflowStatus()->first();

        if ($status === null) {
            return [];
        }

        return [[
            'id' => (int) $status->id,
            'name' => (string) $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
            'count' => 1,
        ]];
    }

    /**
     * The explicit single-model path: only the two columns the aggregation
     * needs, with the status eager-loaded in the same round trip.
     *
     * @return Collection<int, Quote>
     */
    private function fetchQuotes(Opportunity $opportunity): Collection
    {
        return $opportunity->quotes()
            ->select(['id', 'opportunity_id', 'quote_status_id'])
            ->with('quoteStatus')
            ->get();
    }
}
