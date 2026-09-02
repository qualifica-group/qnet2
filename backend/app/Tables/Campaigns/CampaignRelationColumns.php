<?php

namespace App\Tables\Campaigns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `business_function`/`product_category` AGGREGATED (to-many) derived
 * columns for the `campaigns` domain (spec 0094), extracted out of
 * CampaignsTableDefinition (file-size split, engineering.md §6), mirroring
 * OpportunityRelationColumns::AGGREGATED_RELATIONS. Unlike the Project/
 * Opportunity siblings, a campaign's EFFECTIVE rows are its OWN
 * (`campaign_product_lines`) when standalone, or the linked project's
 * (`project_product_lines`) when derived (BR-2, AC-027) — so every
 * operation here is an own-OR-project union, the SAME own-or-through-project
 * pattern already used for `pipeline_status` (CampaignPipelineStatusResolver).
 */
final class CampaignRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @var array<string, array{ownRelation: string, projectRelation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_category' => [
            'ownRelation' => 'productLines.productCategory',
            'projectRelation' => 'project.productLines.productCategory',
            'table' => 'product_categories',
            'fk' => 'product_category_id',
        ],
        'business_function' => [
            'ownRelation' => 'productLines.businessFunction',
            'projectRelation' => 'project.productLines.businessFunction',
            'table' => 'business_functions',
            'fk' => 'business_function_id',
        ],
    ];

    /**
     * The `product_category`/`business_function` set filters: a campaign
     * matches when EITHER its own `productLines` OR its linked project's
     * carries a matching name (own-or-through-project, BR-2). Returns false
     * for any other column id.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        $config = self::AGGREGATED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $query->where(function (Builder $group) use ($config, $values): void {
                $group->whereHas($config['ownRelation'], static function (Builder $relatedQuery) use ($values): void {
                    $relatedQuery->whereIn('name', $values);
                })->orWhereHas($config['projectRelation'], static function (Builder $relatedQuery) use ($values): void {
                    $relatedQuery->whereIn('name', $values);
                });
            });
        }

        return true;
    }

    /**
     * Neither AGGREGATED column is sortable (AC-025/AC-027): no single
     * related row to order by.
     */
    public function isSortable(string $columnId): bool
    {
        return ! array_key_exists($columnId, self::AGGREGATED_RELATIONS);
    }

    /**
     * Excel-like distinct values (spec 0004/0005, AC-026): the union of
     * names reachable via the campaigns' OWN `campaign_product_lines` and
     * via their linked projects' `project_product_lines`, scoped to the
     * campaigns matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        $config = self::AGGREGATED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $campaignIds = (clone $query)->select('id');
        $linkedProjectIds = (clone $query)->whereNotNull('project_id')->pluck('project_id');

        $ownNames = DB::table('campaign_product_lines')
            ->join($config['table'], "{$config['table']}.id", '=', "campaign_product_lines.{$config['fk']}")
            ->whereIn('campaign_product_lines.campaign_id', $campaignIds)
            ->pluck("{$config['table']}.name");

        $projectNames = DB::table('project_product_lines')
            ->join($config['table'], "{$config['table']}.id", '=', "project_product_lines.{$config['fk']}")
            ->whereIn('project_product_lines.project_id', $linkedProjectIds)
            ->pluck("{$config['table']}.name");

        $names = $ownNames->merge($projectNames)
            ->map(static fn (mixed $name): string => (string) $name)
            ->unique();

        if ($search !== null && $search !== '') {
            $names = $names->filter(fn (string $name): bool => stripos($name, $search) !== false);
        }

        return $names->sort()->values()->take($limit)->all();
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }
}
