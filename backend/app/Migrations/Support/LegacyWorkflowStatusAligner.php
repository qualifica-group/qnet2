<?php

namespace App\Migrations\Support;

use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrderWorkflows\WorkOrderWorkflowStatusWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aligns the statuses of ONE workflow set to what the legacy sends (spec 0324,
 * D-3/D-4): the legacy status carrying a `system_key` becomes the system row of
 * that key, replacing the default row the writer created. Used both right after
 * a fresh import (every legacy status exists as a custom row, the default rows
 * are still in place) and on a re-run of an already imported set, so there is a
 * single code path.
 *
 * Names and percentages of rows already imported are never touched. Runs in one
 * transaction without activity log; the caller learns whether anything changed.
 */
class LegacyWorkflowStatusAligner
{
    public function __construct(private readonly WorkOrderWorkflowStatusWriter $writer) {}

    /**
     * @param  list<array{old_id: int, name: string, progress_percentage: ?int, system_key: ?string, group: string}>  $statuses
     * @return bool whether the set changed
     */
    public function align(int $workflowId, array $statuses): bool
    {
        return activity()->withoutLogs(fn (): bool => DB::transaction(function () use ($workflowId, $statuses): bool {
            $before = $this->snapshot($workflowId);
            $legacy = collect($statuses)->keyBy('old_id');

            // Step 1: rows that held a key the legacy no longer assigns to them go back to custom
            $this->demoteUnmarked($workflowId, $legacy);

            // Step 2: each legacy status with a key replaces the default row of that key
            $this->adoptKeys($workflowId, $legacy);

            // Step 3: groups of the remaining legacy rows follow the legacy
            $this->syncGroups($workflowId, $legacy);

            // Step 4: a key left without a row gets its default back, then the set is re-ordered
            $this->writer->restoreMissingSystemRows($workflowId);
            $this->writer->resequence($workflowId, $this->orderedCustomIds($workflowId, $statuses));

            return $this->snapshot($workflowId) !== $before;
        }));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $legacy
     */
    private function demoteUnmarked(int $workflowId, Collection $legacy): void
    {
        foreach ($this->rows($workflowId)->whereNotNull('system_key')->whereNotNull('old_id') as $row) {
            $entry = $legacy->get($row->old_id);

            if ($entry !== null && $entry['system_key'] === $row->system_key) {
                continue;
            }

            $row->forceFill(['system_key' => null, 'group' => $entry['group'] ?? $row->group])->save();
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $legacy
     */
    private function adoptKeys(int $workflowId, Collection $legacy): void
    {
        foreach ($legacy->whereNotNull('system_key') as $entry) {
            $rows = $this->rows($workflowId);
            $status = $rows->firstWhere('old_id', $entry['old_id']);

            if ($status === null || $status->system_key === $entry['system_key']) {
                continue;
            }

            $default = $rows->firstWhere('system_key', $entry['system_key']);

            if ($default !== null) {
                $this->replaceDefault($default, $status);
            }

            $key = WorkOrderWorkflowStatusSystemKey::from($entry['system_key']);
            $status->forceFill(['system_key' => $key->value, 'group' => $key->group()])->save();
        }
    }

    /**
     * Repoints everything that referenced the default row to its legacy replacement, then drops it.
     */
    private function replaceDefault(WorkOrderWorkflowStatus $default, WorkOrderWorkflowStatus $replacement): void
    {
        foreach (['work_orders', 'task_template_items', 'tasks'] as $table) {
            DB::table($table)
                ->where('work_order_workflow_status_id', $default->id)
                ->update(['work_order_workflow_status_id' => $replacement->id]);
        }

        $default->delete();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $legacy
     */
    private function syncGroups(int $workflowId, Collection $legacy): void
    {
        foreach ($this->rows($workflowId)->whereNull('system_key')->whereNotNull('old_id') as $row) {
            $group = $legacy->get($row->old_id)['group'] ?? null;

            if ($group !== null && $row->group->value !== $group) {
                $row->forceFill(['group' => $group])->save();
            }
        }
    }

    /**
     * Custom rows between 'open' and the tail: legacy rows in legacy order first, any other custom after them.
     *
     * @param  list<array<string, mixed>>  $statuses
     * @return array<int, int>
     */
    private function orderedCustomIds(int $workflowId, array $statuses): array
    {
        $position = array_flip(array_column($statuses, 'old_id'));

        return $this->rows($workflowId)
            ->whereNull('system_key')
            ->sortBy(fn (WorkOrderWorkflowStatus $row): array => isset($position[$row->old_id])
                ? [0, $position[$row->old_id], $row->id]
                : [1, $row->sort_order, $row->id])
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, WorkOrderWorkflowStatus>
     */
    private function rows(int $workflowId): Collection
    {
        return WorkOrderWorkflowStatus::query()->where('work_order_workflow_id', $workflowId)->orderBy('id')->get();
    }

    /**
     * @return list<string>
     */
    private function snapshot(int $workflowId): array
    {
        return DB::table('work_order_workflow_statuses')
            ->where('work_order_workflow_id', $workflowId)
            ->orderBy('id')
            ->get(['id', 'system_key', 'group', 'sort_order'])
            ->map(fn (object $row): string => json_encode($row))
            ->all();
    }
}
