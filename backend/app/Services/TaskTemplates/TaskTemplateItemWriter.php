<?php

declare(strict_types=1);

namespace App\Services\TaskTemplates;

use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * `task_template_items` write-side (spec 0124, D-1): mirrors
 * QuoteWorkflows\WorkflowStatusWriter, without that writer's pinned
 * system-row handling — every row here is an ordinary custom one.
 *
 * `sync()` deletes a dropped row through Eloquent's own `::delete()`
 * (never a bulk query), so HasAttachments' `deleting` hook fires and its
 * files are removed from disk too (AC-004/AC-010) — including a `rich_text`
 * image the dropped row's own description embedded.
 *
 * Spec 0128, D-1/D-3: each row's `description` is a rich text field owned by
 * THAT row (a brand-new row needs its own id before an inline image can
 * become one of its attachments), so every write here goes through
 * TaskTemplateDescriptionWriter rather than the row's own attributes() map.
 *
 * `$stageIdsByKey` (spec 0146, D-2), on both create() and sync(), is the
 * `key => id` map App\Services\TaskTemplates\TaskTemplateStageWriter just
 * resolved this same request's `stages` into — TaskTemplateService always
 * runs the stage writer first so this map is ready by the time this class
 * runs.
 */
final class TaskTemplateItemWriter
{
    public function __construct(private readonly TaskTemplateDescriptionWriter $descriptionWriter) {}

    /**
     * Creates every row of a brand-new template, in submission order
     * (`sort_order` = array index, D-1).
     *
     * @param  array<int, TaskTemplateItemData>  $items
     * @param  array<string, int>  $stageIdsByKey
     */
    public function create(TaskTemplate $taskTemplate, array $items, User $actor, array $stageIdsByKey = []): void
    {
        foreach ($items as $index => $item) {
            $row = $taskTemplate->items()->create($item->attributes($index, $stageIdsByKey));
            $this->applyNewDescription($row, $item, $index, $actor);
        }
    }

    /**
     * Full sync (AC-004): an `id` present = update, absent = new row, an
     * existing row not resubmitted = delete (with its attachments).
     * Every submitted `id`'s ownership is already asserted by
     * UpdateTaskTemplateRequest before this runs.
     *
     * @param  array<int, TaskTemplateItemData>  $items
     * @param  array<string, int>  $stageIdsByKey
     */
    public function sync(TaskTemplate $taskTemplate, array $items, User $actor, array $stageIdsByKey = []): void
    {
        /** @var Collection<int, TaskTemplateItem> $existing */
        $existing = $taskTemplate->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $index => $item) {
            if ($item->id === null) {
                $row = $taskTemplate->items()->create($item->attributes($index, $stageIdsByKey));
                $this->applyNewDescription($row, $item, $index, $actor);
                $keptIds[] = $row->id;

                continue;
            }

            /** @var TaskTemplateItem $row */
            $row = $existing->get($item->id);
            $row->fill($item->attributes($index, $stageIdsByKey));
            $this->descriptionWriter->applyOnUpdate($row, $item->description, $actor, $this->field($index));
            $row->save();
            $keptIds[] = $row->id;
        }

        $existing
            ->filter(static fn (TaskTemplateItem $row): bool => ! in_array($row->id, $keptIds, true))
            ->each(static fn (TaskTemplateItem $row) => $row->delete());
    }

    /**
     * A row just created by either create() or sync()'s new-row branch: it
     * already has an id (attributes() carries no `description`, so it saved
     * as null), the description is applied on top and only re-saved if it
     * actually changed.
     */
    private function applyNewDescription(TaskTemplateItem $row, TaskTemplateItemData $item, int $index, User $actor): void
    {
        $this->descriptionWriter->applyOnCreate($row, $item->description, $actor, $this->field($index));

        if ($row->isDirty('description')) {
            $row->save();
        }
    }

    private function field(int $index): string
    {
        return "items.{$index}.description";
    }
}
