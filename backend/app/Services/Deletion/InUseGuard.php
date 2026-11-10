<?php

declare(strict_types=1);

namespace App\Services\Deletion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Refuses (409) the deletion of a configuration row that is still used
 * somewhere (spec 0384), naming each use with its count so the user knows
 * what to change first. The uses come from UsageMap; callers with uses that
 * are not foreign keys (JSON values) pass their own counts to assertNoUsage().
 */
final class InUseGuard
{
    private const string LIST_SEPARATOR = ', ';

    public function assertUnused(Model $model): void
    {
        $this->assertNoUsage($this->usageCounts($model));
    }

    /**
     * @param  array<string, int>  $counts  `deletion.usages.*` label => number of uses
     */
    public function assertNoUsage(array $counts): void
    {
        $parts = [];
        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $parts[] = trans_choice("deletion.usages.{$label}", $count, ['count' => $count]);
            }
        }

        if ($parts !== []) {
            abort(409, __('deletion.in_use', ['usages' => implode(self::LIST_SEPARATOR, $parts)]));
        }
    }

    /**
     * Uses of the row declared in UsageMap, summed per label.
     *
     * @return array<string, int>
     */
    public function usageCounts(Model $model): array
    {
        $table = $model->getTable();
        if (! array_key_exists($table, UsageMap::USAGES)) {
            throw new LogicException("No usage map entry for [{$table}].");
        }

        $counts = [];
        foreach (UsageMap::USAGES[$table] as $reference => $label) {
            [$usingTable, $column] = explode('.', $reference, 2);
            $counts[$label] = ($counts[$label] ?? 0)
                + DB::table($usingTable)->where($column, $model->getKey())->count();
        }

        return $counts;
    }
}
