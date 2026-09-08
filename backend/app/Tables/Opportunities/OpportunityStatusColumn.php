<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

use App\Models\QuoteWorkflowStatus;
use App\Services\Opportunities\OpportunityDefaultStatusResolver;
use App\Services\Opportunities\OpportunityStatusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The COMPUTED "Stato" grid column (spec 0082), shared by the `opportunities`
 * and `request-management` domains — the two grids over the same rows.
 *
 * It is not backed by any column or FK: the cell value is the summary
 * App\Services\Opportunities\OpportunityStatusResolver produces, so the column
 * is NOT sortable (an aggregate has no single sort key — same treatment as
 * `product_category`/`business_function`).
 *
 * The set filter (BR-5) matches the status actually DISPLAYED: an opportunity
 * with quotes shows its quotes' workflow statuses, one without shows the
 * `open` row of the workflow its own product category resolves to (D-8, user
 * directive 2026-09-08). So the option list here is the union of both, and
 * the matching itself is delegated to the shared OpportunityStatusScope —
 * the same predicate every other consumer uses.
 */
final class OpportunityStatusColumn
{
    public const string COLUMN_ID = 'status';

    /** Maximum number of names honoured in the set filter (caps the WHERE IN cardinality, defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @return array<string, mixed>
     */
    public static function declaration(string $label): array
    {
        return [
            'id' => self::COLUMN_ID,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public static function applyFilter(Builder $query, array $values): void
    {
        $values = array_slice($values, 0, self::MAX_FILTER_VALUES);

        if ($values === []) {
            return;
        }

        // The grid query is standalone (and already carries the actor's own
        // visibility), so the quote-less branch resolves over exactly the rows
        // this grid can show — never the whole table.
        OpportunityStatusScope::whereNameIn($query, $values, (clone $query));
    }

    /**
     * Excel-like distinct values (spec 0004/0005): every quote-workflow-
     * status name in use among the matching rows' quotes, plus the names the
     * QUOTE-LESS rows display (D-8) — resolved per row, since each of them
     * follows its own product category's workflow.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public static function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $opportunityIds = (clone $query)->select('opportunities.id');

        $quoteStatusNames = DB::table('quotes')
            ->join('quote_workflow_statuses', 'quote_workflow_statuses.id', '=', 'quotes.quote_workflow_status_id')
            ->whereIn('quotes.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', static function ($builder) use ($search): void {
                $builder->where('quote_workflow_statuses.name', 'like', '%'.self::escapeLike($search).'%');
            })
            ->distinct()
            ->limit($limit)
            ->pluck('quote_workflow_statuses.name')
            ->map(static fn (mixed $name): string => (string) $name);

        return $quoteStatusNames
            ->merge(self::quoteLessNames($query, $search))
            ->unique()
            ->sort()
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * The distinct names the quote-less rows of $query display, filtered by
     * $search the same way the SQL side filters the quotes' own names.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private static function quoteLessNames(Builder $query, ?string $search): array
    {
        $names = collect(app(OpportunityDefaultStatusResolver::class)->statusesForQuoteLess($query))
            ->map(static fn (QuoteWorkflowStatus $status): string => (string) $status->name);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $names = $names->filter(static fn (string $name): bool => str_contains(mb_strtolower($name), $needle));
        }

        return $names->unique()->values()->all();
    }

    /** Escape LIKE wildcards in user input so they are treated literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
