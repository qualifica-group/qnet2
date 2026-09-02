<?php

namespace App\Tables\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `business_function`/`product_category` AGGREGATED (to-many, via
 * `productLines`) derived columns for the `projects` domain (spec 0094),
 * extracted out of ProjectsTableDefinition (file-size split,
 * engineering.md §6), mirroring OpportunityRelationColumns::
 * AGGREGATED_RELATIONS exactly: a `whereHas` set filter (allow-listed
 * columns only, never orderByRaw/whereRaw on raw input — backend.md §8),
 * never sortable (no single related row to order by), and Excel-like
 * distinct values via a join through `project_product_lines`.
 */
final class ProjectRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_category' => ['relation' => 'productLines.productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
        'business_function' => ['relation' => 'productLines.businessFunction', 'table' => 'business_functions', 'fk' => 'business_function_id'],
    ];

    /**
     * The `product_category`/`business_function` set filters via `whereHas`
     * on the nested `productLines` relation's related row name. Returns
     * false for any other column id (falls through to the caller's own
     * DERIVED_RELATIONS handling).
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
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn('name', $values);
            });
        }

        return true;
    }

    /**
     * Neither AGGREGATED column is sortable (AC-025): no single related row
     * to order by, mirroring OpportunityRelationColumns.
     */
    public function isSortable(string $columnId): bool
    {
        return ! array_key_exists($columnId, self::AGGREGATED_RELATIONS);
    }

    /**
     * Excel-like distinct values (spec 0004/0005, AC-026): the related row's
     * name for `product_category`/`business_function`, via a join through
     * `project_product_lines` — scoped to the projects matching $query.
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

        $projectIds = (clone $query)->select('id');

        return DB::table('project_product_lines')
            ->join($config['table'], "{$config['table']}.id", '=', "project_product_lines.{$config['fk']}")
            ->whereIn('project_product_lines.project_id', $projectIds)
            ->when($search !== null && $search !== '', function ($builder) use ($config, $search): void {
                $builder->where("{$config['table']}.name", 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy("{$config['table']}.name")
            ->limit($limit)
            ->pluck("{$config['table']}.name")
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
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

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
