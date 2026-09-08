<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * The Opportunity's status, COMPUTED (spec 0082, re-targeted at the Quote
 * workflow by spec 0083 D-2/D-8): an Opportunity carries no status FK of its
 * own — its state is read off the `quote_workflow_status` of its Quotes,
 * falling back — when it has no Quote yet — to the `open` row of the workflow
 * its OWN classification resolves to (user directive 2026-09-08, delegated to
 * OpportunityDefaultStatusResolver), and only to the GLOBAL default set when
 * nothing matches it. Always a real, user-configurable row, never a hardcoded
 * string.
 *
 * The single source of truth for BR-1..BR-4: every consumer (OpportunityResource,
 * OpportunitiesTableDefinition, RequestManagementResource, RewardResource,
 * notifications) projects the shape produced here, never its own aggregation.
 *
 * Callers on a collection MUST eager-load self::EAGER_LOADS — the in-memory
 * path is the only one that stays O(1) in queries across a table page. On a
 * bare, route-bound model the resolver falls back to ONE explicit aggregate
 * query (never a lazy relation access, so Model::preventLazyLoading() stays
 * satisfied). The quote-less fallback resolves in memory too, on the
 * workflow set OpportunityDefaultStatusResolver memoizes for the whole
 * request — a page of quote-less rows costs the workflow queries once, not
 * once per row.
 */
final class OpportunityStatusResolver
{
    /** At least one Quote: the status is described by the quotes' own workflow statuses. */
    public const string SOURCE_QUOTES = 'quotes';

    /** No Quote at all: the status falls back to the `open` row of the workflow resolved for the Opportunity itself. */
    public const string SOURCE_DEFAULT = 'default';

    /**
     * The relations resolve() reads — its own, plus the ones the quote-less
     * fallback resolves through. Eager-load these whenever the resolver runs
     * over more than one Opportunity.
     *
     * @var array<int, string>
     */
    public const array EAGER_LOADS = ['quotes.quoteWorkflowStatus', ...OpportunityDefaultStatusResolver::EAGER_LOADS];

    public function __construct(private readonly OpportunityDefaultStatusResolver $defaultStatus) {}

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

        // Step 2 (D-8): no quote — fall back to the `open` row of the
        // workflow this Opportunity's own classification resolves to, a
        // single entry with count 0.
        return $this->summary(self::SOURCE_DEFAULT, [$this->defaultEntry($opportunity)]);
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
     * One entry per DISTINCT quote status as DISPLAYED (BR-1), carrying how
     * many quotes sit in it, ordered by the status' own `sort_order`.
     *
     * Grouping is by status NAME, not by `quote_workflow_status_id`: every
     * workflow owns its own status rows (D-6), so two quotes of the same
     * opportunity driven by different workflows carry different ids for the
     * very same state. Grouping by id would show "2 stati" for two quotes both
     * in "Da qualificare" — the badge names the status, so the name is its
     * identity here too (the same key the set filter and OpportunityStatusScope
     * already match on).
     *
     * @return array<int, array{id: int, name: string, color: string|null, group: string, count: int}>
     */
    private function quoteEntries(Opportunity $opportunity): array
    {
        $quotes = $opportunity->relationLoaded('quotes')
            ? $opportunity->quotes
            : $this->fetchQuotes($opportunity);

        return $quotes
            ->filter(static fn (Quote $quote): bool => $quote->quoteWorkflowStatus !== null)
            ->groupBy(static fn (Quote $quote): string => self::nameKey((string) $quote->quoteWorkflowStatus->name))
            ->map(static function (Collection $group): array {
                $status = self::representativeStatus($group);

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
     * The row that represents a same-named group: the lowest `sort_order`
     * (ties broken by id) so id/color/group stay stable whatever order the
     * quotes come back in.
     *
     * @param  Collection<int, Quote>  $group
     */
    private static function representativeStatus(Collection $group): QuoteWorkflowStatus
    {
        return $group
            ->map(static fn (Quote $quote): QuoteWorkflowStatus => $quote->quoteWorkflowStatus)
            ->sortBy('id')
            ->sortBy('sort_order')
            ->first();
    }

    /** Case- and whitespace-insensitive identity of a displayed status name. */
    private static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * The fallback entry (D-8, user directive 2026-09-08): the `open` row of
     * the workflow OpportunityDefaultStatusResolver resolves for
     * $opportunity — its product lines' categories being what a quote-less
     * row has to be classified by — with `count` always 0 (there is no quote
     * to count).
     *
     * @return array{id: int, name: string, color: string|null, group: string, count: int}
     */
    private function defaultEntry(Opportunity $opportunity): array
    {
        $status = $this->defaultStatus->statusFor($opportunity);

        return [
            'id' => (int) $status->id,
            'name' => (string) $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
            'count' => 0,
        ];
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
            ->select(['id', 'opportunity_id', 'quote_workflow_status_id'])
            ->with('quoteWorkflowStatus')
            ->get();
    }
}
