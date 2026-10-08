<?php

namespace App\Tables\Concerns;

use App\Models\User;
use App\Services\Table\RowGroupQuery;

/**
 * Opt-in server-side row grouping (spec 0197, D-4): the defaults every
 * definition inherits (no grouping) plus the config decoration for the ones
 * that override supportsRowGroups(). Same opt-in shape as tree/kanban.
 */
trait ResolvesRowGroups
{
    /**
     * Default: no row grouping. A domain opts in by overriding.
     */
    public function supportsRowGroups(): bool
    {
        return false;
    }

    /**
     * Default: nothing is groupable.
     *
     * @return array<string, array{key: string, labels: array<int, string>}>
     */
    public function groupableColumns(User $actor): array
    {
        return [];
    }

    /**
     * Default: no per-group aggregates.
     *
     * @return array<string, array{func: string, expression: string}>
     */
    public function groupAggregates(User $actor): array
    {
        return [];
    }

    /**
     * Adds `row_grouping` plus the per-column `groupable`/`aggFunc` flags to
     * the resolved config; a no-op for a domain without the opt-in, so every
     * other config stays byte-identical.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function withRowGrouping(array $config, User $actor): array
    {
        if (! $this->supportsRowGroups()) {
            return $config;
        }

        $groupable = array_keys($this->groupableColumns($actor));
        $aggregates = $this->groupAggregates($actor);

        $config['columns'] = array_map(
            static function (array $column) use ($groupable, $aggregates): array {
                $column['groupable'] = in_array($column['id'], $groupable, true);
                $column['aggFunc'] = $aggregates[$column['id']]['func'] ?? null;

                return $column;
            },
            $config['columns'],
        );

        $config['row_grouping'] = [
            'enabled' => true,
            'max_depth' => RowGroupQuery::MAX_DEPTH,
            'columns' => $groupable,
        ];

        return $config;
    }
}
