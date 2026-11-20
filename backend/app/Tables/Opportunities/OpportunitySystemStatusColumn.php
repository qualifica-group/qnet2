<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

use App\Enums\WorkflowStatusSystemKey;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteSystemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "Stato di sistema" column of the Opportunita' grid (spec 0409): the
 * system statuses (spec 0404 part A) of the opportunity's offers, reached
 * through their working status (quotes -> quote_workflow_statuses ->
 * quote_system_statuses). An opportunity without offers shows the `open`
 * system status, since its computed `status` is the `open` working row of its
 * set (spec 0083 D-8), always linked to it (spec 0404 A-D4). The status tabs
 * filter on it and count on it. Bound values and fixed identifiers only.
 */
final class OpportunitySystemStatusColumn
{
    public const string COLUMN_ID = 'quote_system_status';

    /** Eager load that keeps summarize() query-free across a page. */
    public const string RELATION = 'quotes.quoteWorkflowStatus.systemStatus';

    private const string SOURCE_QUOTES = 'quotes';

    private const string SOURCE_DEFAULT = 'default';

    private const int MAX_FILTER_VALUES = 200;

    /** Memo of the `open` system status: one lookup per grid request (the table registry builds a fresh definition per call). */
    private ?QuoteSystemStatus $openStatus = null;

    private bool $openStatusLoaded = false;

    /**
     * @return array<string, mixed>
     */
    public static function declaration(string $label): array
    {
        return [
            'id' => self::COLUMN_ID,
            'label' => $label,
            'type' => 'text',
            'visible' => false,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * Rows with an offer in one of the named system statuses, plus the rows
     * without offers when the `open` system status is among them.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $names
     */
    public function applyFilter(Builder $query, array $names): void
    {
        $names = array_slice($names, 0, self::MAX_FILTER_VALUES);

        if ($names === []) {
            return;
        }

        $includesQuoteLess = in_array($this->openStatus()?->name, $names, true);

        $query->where(static function (Builder $group) use ($names, $includesQuoteLess): void {
            $group->whereHas(self::RELATION, static fn (Builder $status) => $status->whereIn('name', $names));

            if ($includesQuoteLess) {
                $group->orWhereDoesntHave('quotes');
            }
        });
    }

    /**
     * Excel-like distinct values (spec 0004/0005), in the configured order.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $usedIds = DB::table('quotes')
            ->join('quote_workflow_statuses', 'quote_workflow_statuses.id', '=', 'quotes.quote_workflow_status_id')
            ->whereIn('quotes.opportunity_id', (clone $query)->reorder()->select('opportunities.id'))
            ->select('quote_workflow_statuses.quote_system_status_id');

        $openId = (clone $query)->whereDoesntHave('quotes')->exists() ? $this->openStatus()?->id : null;

        return QuoteSystemStatus::query()
            ->where(static fn ($builder) => $builder->whereIn('id', $usedIds)->when($openId !== null, static fn ($inner) => $inner->orWhere('id', $openId)))
            ->when($search !== null && $search !== '', static fn ($builder) => $builder->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'))
            ->orderBy('sort_order')
            ->limit($limit)
            ->pluck('name')
            ->all();
    }

    /**
     * How many opportunities of the filtered set sit on each system status,
     * keyed by name: an opportunity counts once per status of its offers, and
     * in the `open` status when it has none.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function counts(Builder $query): array
    {
        $counts = DB::table('quotes')
            ->join('quote_workflow_statuses', 'quote_workflow_statuses.id', '=', 'quotes.quote_workflow_status_id')
            ->join('quote_system_statuses', 'quote_system_statuses.id', '=', 'quote_workflow_statuses.quote_system_status_id')
            ->whereIn('quotes.opportunity_id', (clone $query)->reorder()->select('opportunities.id'))
            ->groupBy('quote_system_statuses.name')
            ->selectRaw('quote_system_statuses.name as status, COUNT(DISTINCT quotes.opportunity_id) as total')
            ->pluck('total', 'status')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $open = $this->openStatus();
        $quoteLess = $open === null ? 0 : (clone $query)->reorder()->whereDoesntHave('quotes')->count();

        if ($quoteLess > 0) {
            $counts[$open->name] = ($counts[$open->name] ?? 0) + $quoteLess;
        }

        return $counts;
    }

    /**
     * The cell: one entry per distinct system status of the offers, with how
     * many offers sit in it, in the configured order. Same shape as the
     * `status` column, so the grid draws both with the same badge.
     *
     * @return array{source: string, distinct_count: int, entries: array<int, array{id: int, name: string, color: string|null, group: string, count: int}>}|null
     */
    public function summarize(Opportunity $opportunity): ?array
    {
        $statuses = $opportunity->quotes
            ->map(static fn (Quote $quote): ?QuoteSystemStatus => $quote->quoteWorkflowStatus?->systemStatus)
            ->filter();

        if ($opportunity->quotes->isNotEmpty()) {
            return self::summary(self::SOURCE_QUOTES, $statuses->groupBy('id')->map(
                static fn (Collection $group): array => self::entry($group->first(), $group->count()),
            ));
        }

        $open = $this->openStatus();

        return $open === null ? null : self::summary(self::SOURCE_DEFAULT, collect([self::entry($open, 0)]));
    }

    /**
     * @param  Collection<int|string, array{id: int, name: string, color: string|null, group: string, count: int, sort_order: int}>  $entries
     * @return array{source: string, distinct_count: int, entries: array<int, array{id: int, name: string, color: string|null, group: string, count: int}>}
     */
    private static function summary(string $source, Collection $entries): array
    {
        $ordered = $entries->sortBy('sort_order')
            ->map(static fn (array $entry): array => array_diff_key($entry, ['sort_order' => true]))
            ->values()
            ->all();

        return ['source' => $source, 'distinct_count' => count($ordered), 'entries' => $ordered];
    }

    /**
     * @return array{id: int, name: string, color: string|null, group: string, count: int, sort_order: int}
     */
    private static function entry(QuoteSystemStatus $status, int $count): array
    {
        return [
            'id' => (int) $status->id,
            'name' => (string) $status->name,
            'color' => $status->color,
            'group' => $status->behavior->value,
            'count' => $count,
            'sort_order' => (int) $status->sort_order,
        ];
    }

    private function openStatus(): ?QuoteSystemStatus
    {
        if (! $this->openStatusLoaded) {
            $this->openStatus = QuoteSystemStatus::query()->where('system_key', WorkflowStatusSystemKey::Open->value)->first();
            $this->openStatusLoaded = true;
        }

        return $this->openStatus;
    }
}
