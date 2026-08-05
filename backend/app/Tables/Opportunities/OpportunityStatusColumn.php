<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

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
 * The set filter (BR-5) matches the status actually DISPLAYED, which spans two
 * vocabularies: an opportunity with quotes shows its quote statuses, one
 * without shows its working state. So the option list here is the union of
 * both names, and the matching itself is delegated to the shared
 * OpportunityStatusScope — the same predicate every other consumer uses.
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

        OpportunityStatusScope::whereNameIn($query, $values);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): every quote-status name in
     * use among the matching rows, plus every working-state name in use among
     * the QUOTE-LESS matching rows — the exact set of labels the column can
     * draw, merged and sorted as one list.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public static function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $opportunityIds = (clone $query)->select('opportunities.id');

        $quoteStatusNames = DB::table('quotes')
            ->join('quote_statuses', 'quote_statuses.id', '=', 'quotes.quote_status_id')
            ->whereIn('quotes.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', static function ($builder) use ($search): void {
                $builder->where('quote_statuses.name', 'like', '%'.self::escapeLike($search).'%');
            })
            ->distinct()
            ->limit($limit)
            ->pluck('quote_statuses.name');

        $workflowStatusIds = (clone $query)
            ->whereDoesntHave('quotes')
            ->whereNotNull('opportunity_workflow_status_id')
            ->select('opportunity_workflow_status_id');

        $workflowStatusNames = DB::table('opportunity_workflow_statuses')
            ->whereIn('id', $workflowStatusIds)
            ->when($search !== null && $search !== '', static function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.self::escapeLike($search).'%');
            })
            ->distinct()
            ->limit($limit)
            ->pluck('name');

        return $quoteStatusNames
            ->merge($workflowStatusNames)
            ->map(static fn (mixed $name): string => (string) $name)
            ->unique()
            ->sort()
            ->values()
            ->take($limit)
            ->all();
    }

    /** Escape LIKE wildcards in user input so they are treated literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
