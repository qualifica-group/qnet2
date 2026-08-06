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
 * The set filter (BR-5) matches the status actually DISPLAYED: an opportunity
 * with quotes shows its quotes' workflow statuses, one without shows the
 * GLOBAL default workflow set's `open` row (D-8, spec 0083). So the option
 * list here is the union of both, and the matching itself is delegated to
 * the shared OpportunityStatusScope — the same predicate every other
 * consumer uses.
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
     * Excel-like distinct values (spec 0004/0005): every quote-workflow-
     * status name in use among the matching rows' quotes, plus — when at
     * least one matching row has no quote (D-8) — the GLOBAL default
     * workflow set's `open` row name, the ONE value every such row displays.
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

        $hasQuoteLessRow = (clone $query)->whereDoesntHave('quotes')->exists();
        $defaultOpenName = $hasQuoteLessRow ? self::defaultOpenName($search) : null;

        return $quoteStatusNames
            ->when($defaultOpenName !== null, static fn ($collection) => $collection->push($defaultOpenName))
            ->unique()
            ->sort()
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * The GLOBAL default workflow set's `open` row name (D-8) — the ONE
     * value every quote-less opportunity's status resolves to — or null when
     * $search does not match it (or the row is somehow missing, defense in
     * depth; never expected — AC-004/AC-005).
     */
    private static function defaultOpenName(?string $search): ?string
    {
        $name = DB::table('quote_workflow_statuses')
            ->whereNull('quote_workflow_id')
            ->where('system_key', 'open')
            ->value('name');

        if (! is_string($name)) {
            return null;
        }

        if ($search !== null && $search !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($search))) {
            return null;
        }

        return $name;
    }

    /** Escape LIKE wildcards in user input so they are treated literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
