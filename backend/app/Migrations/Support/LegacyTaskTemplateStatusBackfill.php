<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use Illuminate\Support\Collection;

/**
 * Backfills the working status link on the actions of a task template
 * imported before spec 0323 (amendment D-11). The actions carry no `old_id`,
 * so they are matched by position: the import creates them in the legacy
 * order, and the match is trusted only while count and titles are still the
 * imported ones. A link already set in qnet-2 is never overwritten. Plain
 * query updates: no activity log, like every other migration write.
 */
final class LegacyTaskTemplateStatusBackfill
{
    /**
     * @param  list<TaskTemplateItemData>  $legacyItems  the legacy actions, mapped like a fresh import
     * @param  array<int, string>  $warnings
     * @return bool whether any action was linked
     */
    public function fill(TaskTemplate $taskTemplate, array $legacyItems, array &$warnings): bool
    {
        // Step 1: nothing to backfill when the legacy links no action
        if (! collect($legacyItems)->contains(fn (TaskTemplateItemData $item): bool => $item->workOrderWorkflowStatusId !== null)) {
            return false;
        }

        // Step 2: the qnet-2 actions in creation order must still mirror the legacy tree
        $items = $taskTemplate->items()->reorder()->orderBy('id')->get(['id', 'title', 'work_order_workflow_status_id']);

        if (! $this->sameTree($items, $legacyItems)) {
            $warnings[] = 'Task template actions changed in qnet-2 since the import; the working status links were not backfilled.';

            return false;
        }

        // Step 3: fill only the empty links
        $changed = false;

        foreach ($items->values() as $index => $item) {
            $statusId = $legacyItems[$index]->workOrderWorkflowStatusId;

            if ($statusId === null || $item->work_order_workflow_status_id !== null) {
                continue;
            }

            TaskTemplateItem::query()->whereKey($item->id)->update(['work_order_workflow_status_id' => $statusId]);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param  Collection<int, TaskTemplateItem>  $items
     * @param  list<TaskTemplateItemData>  $legacyItems
     */
    private function sameTree(Collection $items, array $legacyItems): bool
    {
        return $items->pluck('title')->all() === array_map(fn (TaskTemplateItemData $item): string => $item->title, $legacyItems);
    }
}
