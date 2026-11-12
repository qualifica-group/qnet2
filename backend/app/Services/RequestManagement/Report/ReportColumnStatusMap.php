<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\ReportStatusColumn;
use App\Models\ReportColumnStatus;
use Illuminate\Support\Facades\DB;

/**
 * The configured working statuses of every status-based statistics column
 * (spec 0391 D-4). Read ONCE per instance: a report run resolves one map and
 * every branch, column and twin reads it from memory.
 */
final class ReportColumnStatusMap
{
    /** @var array<string, array<int, int>>|null */
    private ?array $statusIds = null;

    /**
     * @return array<int, int> ascending; empty when nothing is chosen
     */
    public function idsFor(ReportStatusColumn $column): array
    {
        return $this->all()[$column->value];
    }

    /**
     * @return array<string, array<int, int>> every column key, in enum order
     */
    public function all(): array
    {
        if ($this->statusIds !== null) {
            return $this->statusIds;
        }

        $stored = ReportColumnStatus::query()
            ->orderBy('quote_workflow_status_id')
            ->get(['column_key', 'quote_workflow_status_id'])
            ->groupBy('column_key');

        $map = [];
        foreach (ReportStatusColumn::cases() as $column) {
            $map[$column->value] = $stored->get($column->value, collect())->pluck('quote_workflow_status_id')->map(static fn (mixed $id): int => (int) $id)->all();
        }

        return $this->statusIds = $map;
    }

    /**
     * Replaces the whole configuration.
     *
     * @param  array<string, array<int, int>>  $statusIdsByColumn  every ReportStatusColumn value
     */
    public function replace(array $statusIdsByColumn): void
    {
        DB::transaction(function () use ($statusIdsByColumn): void {
            ReportColumnStatus::query()->delete();

            $now = now();
            $rows = [];
            foreach (ReportStatusColumn::cases() as $column) {
                foreach (array_unique($statusIdsByColumn[$column->value] ?? []) as $statusId) {
                    $rows[] = ['column_key' => $column->value, 'quote_workflow_status_id' => (int) $statusId, 'created_at' => $now, 'updated_at' => $now];
                }
            }

            ReportColumnStatus::query()->insert($rows);
        });

        $this->statusIds = null;
    }
}
