<?php

namespace App\Services\Table;

use App\DataObjects\Table\RowsResult;
use App\Models\User;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Server-side row grouping for the generic SSRM engine (spec 0197, D-4).
 *
 * Every SQL fragment used for WHERE / GROUP BY / ORDER BY comes from the
 * definition's own allow-lists (groupableColumns() / groupAggregates()),
 * which TableRowsRequest has already matched against the request; request
 * values only ever travel as bound parameters.
 */
final class RowGroupQuery
{
    public const int MAX_DEPTH = 3;

    /** Key of the group holding the rows with no value on the grouped column. */
    public const string NULL_KEY = '__null__';

    public const int KEY_MAX_LENGTH = 191;

    private const string LABEL_SEPARATOR = ' - ';

    /**
     * Narrow $query to the rows under the parent groups named by $keys, one
     * key per grouped column from the outermost in.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $cols
     * @param  array<int, string>  $keys
     */
    public function applyKeys(TableDefinition $definition, User $actor, Builder $query, array $cols, array $keys): void
    {
        $groupable = $definition->groupableColumns($actor);

        foreach (array_values($keys) as $level => $key) {
            $expression = $groupable[$cols[$level]]['key'];

            if ($key === self::NULL_KEY) {
                $query->whereRaw("{$expression} IS NULL");
            } else {
                $query->whereRaw("{$expression} = ?", [$key]);
            }
        }
    }

    /**
     * One page of groups for the first level not yet opened by the keys.
     *
     * @param  Builder<Model>  $query  filtered and already narrowed by the parent keys, unsorted
     * @param  array<string, mixed>  $payload
     */
    public function groupLevel(TableDefinition $definition, User $actor, Builder $query, array $payload, int $offset, int $limit): RowsResult
    {
        // Step 1: the column of the level being opened and its allow-listed SQL
        $columnId = $payload['rowGroupCols'][count($payload['groupKeys'] ?? [])];
        $spec = $definition->groupableColumns($actor)[$columnId];
        $aggregates = $this->aggregateList($definition, $actor);

        // Step 2: one grouped query for the page of groups
        $grouped = $this->plainQuery($query)
            ->selectRaw("{$spec['key']} as group_key")
            ->selectRaw('COUNT(*) as child_count')
            ->groupByRaw($spec['key']);

        foreach ($spec['labels'] as $index => $label) {
            $grouped->selectRaw("MAX({$label}) as label_{$index}");
        }

        foreach ($aggregates as $index => $aggregate) {
            $grouped->selectRaw("COALESCE(SUM({$aggregate['expression']}), 0) as agg_{$index}");
        }

        $this->applySorting($grouped, $payload['sortModel'] ?? [], $columnId, $spec, $aggregates);

        $items = $grouped->offset($offset)->limit($limit)->get()
            ->map(fn (object $row): array => $this->mapGroup($row, $columnId, $spec, $aggregates))
            ->all();

        // Step 3: totals, skipped on a later page whose client already knows them
        $knownTotal = $payload['knownTotal'] ?? null;

        if ($offset > 0 && $knownTotal !== null) {
            return new RowsResult(items: $items, total: (int) $knownTotal, offset: $offset, limit: $limit);
        }

        return new RowsResult(
            items: $items,
            total: $this->countGroups($query, $spec['key']),
            offset: $offset,
            limit: $limit,
            aggregates: $this->totals($definition, $actor, $query),
        );
    }

    /**
     * The aggregate columns as a list, so each gets a positional SQL alias.
     *
     * @return array<int, array{id: string, expression: string}>
     */
    private function aggregateList(TableDefinition $definition, User $actor): array
    {
        $list = [];

        foreach ($definition->groupAggregates($actor) as $id => $aggregate) {
            $list[] = ['id' => $id, 'expression' => $aggregate['expression']];
        }

        return $list;
    }

    /**
     * A bare query-builder copy of $query: same FROM / JOINs / WHEREs, no
     * columns, no ordering, no eager loads.
     *
     * @param  Builder<Model>  $query
     */
    private function plainQuery(Builder $query): QueryBuilder
    {
        return (clone $query)->reorder()->toBase()->select([]);
    }

    /**
     * Groups sort by label asc unless the sortModel names the grouped column
     * or one of the aggregate columns; the key is the final tie-breaker.
     *
     * @param  array<int, array<string, mixed>>  $sortModel
     * @param  array{key: string, labels: array<int, string>}  $spec
     * @param  array<int, array{id: string, expression: string}>  $aggregates
     */
    private function applySorting(QueryBuilder $grouped, array $sortModel, string $columnId, array $spec, array $aggregates): void
    {
        $aggregateAlias = null;
        $direction = 'asc';

        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;
            $aggregateIndex = array_search($colId, array_column($aggregates, 'id'), true);

            if ($colId !== $columnId && $aggregateIndex === false) {
                continue;
            }

            if ($aggregateIndex !== false) {
                $aggregateAlias = "agg_{$aggregateIndex}";
            }

            $direction = ($sort['sort'] ?? null) === 'desc' ? 'desc' : 'asc';
            break;
        }

        if ($aggregateAlias !== null) {
            $grouped->orderBy($aggregateAlias, $direction);
        } else {
            foreach (array_keys($spec['labels']) as $index) {
                $grouped->orderBy("label_{$index}", $direction);
            }
        }

        $grouped->orderByRaw("{$spec['key']} asc");
    }

    /**
     * @param  array{key: string, labels: array<int, string>}  $spec
     * @param  array<int, array{id: string, expression: string}>  $aggregates
     * @return array<string, mixed>
     */
    private function mapGroup(object $row, string $columnId, array $spec, array $aggregates): array
    {
        $parts = [];

        foreach (array_keys($spec['labels']) as $index) {
            $part = $row->{"label_{$index}"};

            if ($part !== null && $part !== '') {
                $parts[] = (string) $part;
            }
        }

        return [
            'group' => true,
            'column' => $columnId,
            'key' => $row->group_key === null ? self::NULL_KEY : (string) $row->group_key,
            'label' => $parts === [] ? null : implode(self::LABEL_SEPARATOR, $parts),
            'child_count' => (int) $row->child_count,
            'aggregates' => $this->aggregateValues($row, $aggregates),
        ];
    }

    /**
     * @param  array<int, array{id: string, expression: string}>  $aggregates
     * @return array<string, string>
     */
    private function aggregateValues(object $row, array $aggregates): array
    {
        $values = [];

        foreach ($aggregates as $index => $aggregate) {
            $values[$aggregate['id']] = number_format(round((float) $row->{"agg_{$index}"}, 2), 2, '.', '');
        }

        return $values;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function countGroups(Builder $query, string $keyExpression): int
    {
        $keys = $this->plainQuery($query)->selectRaw("{$keyExpression} as group_key")->groupByRaw($keyExpression);

        return $keys->getConnection()->query()->fromSub($keys, 'row_groups')->count();
    }

    /**
     * Sums of the aggregate columns the actor may see over the whole $query (every
     * group of the level), as the grid footer shows them; empty when there are none.
     *
     * @param  Builder<Model>  $query
     * @return array<string, string>
     */
    public function totals(TableDefinition $definition, User $actor, Builder $query): array
    {
        $aggregates = $this->aggregateList($definition, $actor);

        if ($aggregates === []) {
            return [];
        }

        $totals = $this->plainQuery($query);

        foreach ($aggregates as $index => $aggregate) {
            $totals->selectRaw("COALESCE(SUM({$aggregate['expression']}), 0) as agg_{$index}");
        }

        return $this->aggregateValues($totals->first(), $aggregates);
    }
}
