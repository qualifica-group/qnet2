<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Tables\TableDefinition;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Text/number/date columns whose distinct values exceed the checklist limit
 * (user decision 2026-11-27): the config marks them `tooManyFilterValues`, so
 * the grid offers only their condition filter (Contiene, date, number) instead
 * of an "Excel" checklist that could list only the first values. A column
 * within the limit keeps the checklist.
 *
 * Probed per actor (grid visibility is per user, spec 0178 D-5) and per
 * category scope, BEFORE the row scopes (opportunity, quote, registry), so the
 * columns and preferences endpoints agree. Cached with stale-while-revalidate:
 * the list changes slowly, and a probe reads every candidate column once.
 */
class TableLargeValueColumnsService
{
    private const string CACHE_PREFIX = 'table-large-value-columns:';

    /** Filter kinds that always get the standalone checklist on the grid (frontend `resolveFilter`). */
    private const array CHECKLIST_ONLY_KINDS = ['set', 'enum', 'tags', 'badge', 'boolean'];

    public function __construct(private readonly TableService $tables) {}

    /**
     * Ids of the definition's columns with more distinct values than the limit.
     *
     * @param  array<int, int|null>  $scope  the category scope already applied to the definition
     * @return list<string>
     */
    public function ids(TableDefinition $definition, User $actor, array $scope): array
    {
        $key = self::CACHE_PREFIX.$actor->getKey().':'.sha1($definition::class.'|'.json_encode($scope));

        return Cache::flexible(
            $key,
            [(int) config('table-filter-values.large_columns_fresh_seconds'), (int) config('table-filter-values.large_columns_stale_seconds')],
            fn (): array => $this->probe($definition, $actor),
        );
    }

    /**
     * Marks the large columns of a resolved config.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $largeIds
     * @return array<string, mixed>
     */
    public function markColumns(array $config, array $largeIds): array
    {
        if ($largeIds === [] || ! isset($config['columns']) || ! is_array($config['columns'])) {
            return $config;
        }

        $config['columns'] = array_map(
            static fn (array $column): array => in_array($column['id'] ?? null, $largeIds, true)
                ? [...$column, 'tooManyFilterValues' => true]
                : $column,
            $config['columns'],
        );

        return $config;
    }

    /** @return list<string> */
    private function probe(TableDefinition $definition, User $actor): array
    {
        $limit = (int) config('table-filter-values.limit');
        $large = [];

        foreach ($this->candidateIds($definition) as $columnId) {
            if ($this->exceedsLimit($definition, $actor, $columnId, $limit)) {
                $large[] = $columnId;
            }
        }

        return $large;
    }

    /** @return list<string> */
    private function candidateIds(TableDefinition $definition): array
    {
        $ids = [];

        foreach ($definition->filterableColumnMap() as $id => $column) {
            $kind = $column['filterType'] ?? $column['type'] ?? null;
            if (($column['hasFilterValues'] ?? true) !== false && ! in_array($kind, self::CHECKLIST_ONLY_KINDS, true)) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /** A column whose probe fails keeps its checklist: the grid must still open. */
    private function exceedsLimit(TableDefinition $definition, User $actor, string $columnId, int $limit): bool
    {
        try {
            return $this->tables->distinctValues($definition, $actor, $columnId, null, [], $limit)->hasMore;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
