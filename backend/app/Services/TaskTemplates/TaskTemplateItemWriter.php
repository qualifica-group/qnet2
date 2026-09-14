<?php

declare(strict_types=1);

namespace App\Services\TaskTemplates;

use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use Illuminate\Support\Collection;

/**
 * `task_template_items` write-side (spec 0124, D-1): mirrors
 * QuoteWorkflows\WorkflowStatusWriter, without that writer's pinned
 * system-row handling — every row here is an ordinary custom one.
 *
 * `sync()` deletes a dropped row through Eloquent's own `::delete()`
 * (never a bulk query), so HasAttachments' `deleting` hook fires and its
 * files are removed from disk too (AC-004/AC-010).
 */
final class TaskTemplateItemWriter
{
    /**
     * Creates every row of a brand-new template, in submission order
     * (`sort_order` = array index, D-1).
     *
     * @param  array<int, TaskTemplateItemData>  $items
     */
    public function create(TaskTemplate $taskTemplate, array $items): void
    {
        foreach ($items as $index => $item) {
            $taskTemplate->items()->create($item->attributes($index));
        }
    }

    /**
     * Full sync (AC-004): an `id` present = update, absent = new row, an
     * existing row not resubmitted = delete (with its attachments).
     * Every submitted `id`'s ownership is already asserted by
     * UpdateTaskTemplateRequest before this runs.
     *
     * @param  array<int, TaskTemplateItemData>  $items
     */
    public function sync(TaskTemplate $taskTemplate, array $items): void
    {
        /** @var Collection<int, TaskTemplateItem> $existing */
        $existing = $taskTemplate->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $index => $item) {
            if ($item->id === null) {
                $created = $taskTemplate->items()->create($item->attributes($index));
                $keptIds[] = $created->id;

                continue;
            }

            /** @var TaskTemplateItem $row */
            $row = $existing->get($item->id);
            $row->fill($item->attributes($index))->save();
            $keptIds[] = $row->id;
        }

        $existing
            ->filter(static fn (TaskTemplateItem $row): bool => ! in_array($row->id, $keptIds, true))
            ->each(static fn (TaskTemplateItem $row) => $row->delete());
    }
}
