<?php

namespace App\Tables\BusinessFunctions;

use App\Models\BusinessFunction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The `*_count` columns of the `business-functions` grid (spec 0281): how many
 * records of each kind the system links to the function. Each count is a
 * correlated subquery in the SELECT list, so no JOIN multiplies the rows and
 * the generic engine sorts on the alias with a plain ORDER BY. The product
 * line tables count DISTINCT owners (D-6): an opportunity with two lines on
 * the same function counts once. Not filterable.
 */
final class BusinessFunctionUsageCounts
{
    /**
     * alias => [FROM, column matched to `business_functions.id`, COUNT() argument].
     * Constants only: nothing here comes from the request.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const array SOURCES = [
        'users_count' => ['business_function_user', 'business_function_user.business_function_id', '*'],
        'product_categories_count' => ['product_categories', 'product_categories.business_function_id', '*'],
        'competences_count' => ['employment_product_lines', 'employment_product_lines.business_function_id', 'DISTINCT employment_product_lines.employment_profile_id'],
        'opportunities_count' => ['opportunity_product_lines', 'opportunity_product_lines.business_function_id', 'DISTINCT opportunity_product_lines.opportunity_id'],
        'projects_count' => ['project_product_lines', 'project_product_lines.business_function_id', 'DISTINCT project_product_lines.project_id'],
        'campaigns_count' => ['campaign_product_lines', 'campaign_product_lines.business_function_id', 'DISTINCT campaign_product_lines.campaign_id'],
        'purchase_requests_count' => ['purchase_requests', 'purchase_requests.business_function_id', '*'],
        'children_count' => ['business_functions as child_functions', 'child_functions.parent_id', '*'],
    ];

    /** The rarer kinds, offered from the column picker only. */
    private const array HIDDEN = ['projects_count', 'campaigns_count', 'purchase_requests_count', 'children_count'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return array_map(static fn (string $alias): array => [
            'id' => $alias,
            'label' => "businessFunctions.columns.{$alias}",
            'type' => 'number',
            'visible' => ! in_array($alias, self::HIDDEN, true),
            'sortable' => true,
            'filterable' => false,
        ], array_keys(self::SOURCES));
    }

    /**
     * @param  Builder<BusinessFunction>  $query
     * @return Builder<BusinessFunction>
     */
    public static function apply(Builder $query): Builder
    {
        $query->select('business_functions.*');

        foreach (self::SOURCES as $alias => [$from, $foreignKey, $counted]) {
            $query->selectSub(
                DB::table($from)->selectRaw("COUNT({$counted})")->whereColumn($foreignKey, 'business_functions.id'),
                $alias,
            );
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public static function summarize(BusinessFunction $row): array
    {
        $counts = [];

        foreach (array_keys(self::SOURCES) as $alias) {
            $counts[$alias] = (int) $row->getAttribute($alias);
        }

        return $counts;
    }
}
