<?php

declare(strict_types=1);

namespace App\Services\TaskTemplates;

use App\DataObjects\TaskTemplates\TaskTemplateStageData;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateStage;
use Illuminate\Support\Collection;

/**
 * `task_template_stages` write-side (spec 0146, D-2): the "Fasi" of a
 * TaskTemplate, written only through the header's own create/update
 * full-sync, mirroring TaskTemplateItemWriter's own shape.
 *
 * Both create() and sync() return a `key => id` map: the request-scoped
 * `stages.*.key` this run just resolved to a real row id, which
 * TaskTemplateItemWriter passes straight into TaskTemplateItemData::
 * attributes() to resolve each item's own `stage_key`. TaskTemplateService
 * always runs this writer BEFORE the item writer for that reason.
 *
 * `sync()` deletes a dropped stage through Eloquent's own `::delete()`
 * (never a bulk query): the FK's own `nullOnDelete` demotes every item still
 * pointing at it to "Senza fase" regardless (AC-002/AC-005) — this is only
 * about keeping LogsModelActivity's per-row audit trail honest, the same
 * reasoning as TaskTemplateItemWriter's own row-by-row delete.
 */
final class TaskTemplateStageWriter
{
    /**
     * Creates every stage of a brand-new template, in submission order
     * (`sort_order` = array index, D-2).
     *
     * @param  array<int, TaskTemplateStageData>  $stages
     * @return array<string, int>
     */
    public function create(TaskTemplate $taskTemplate, array $stages): array
    {
        $idsByKey = [];

        foreach ($stages as $index => $stage) {
            $row = $taskTemplate->stages()->create(['name' => $stage->name, 'sort_order' => $index]);
            $idsByKey[$stage->key] = $row->id;
        }

        return $idsByKey;
    }

    /**
     * Full sync (AC-002): an `id` present = update, absent = new row, an
     * existing row not resubmitted = delete. Every submitted `id`'s
     * ownership is already asserted by UpdateTaskTemplateRequest before this
     * runs.
     *
     * @param  array<int, TaskTemplateStageData>  $stages
     * @return array<string, int>
     */
    public function sync(TaskTemplate $taskTemplate, array $stages): array
    {
        /** @var Collection<int, TaskTemplateStage> $existing */
        $existing = $taskTemplate->stages()->get()->keyBy('id');
        $keptIds = [];
        $idsByKey = [];

        foreach ($stages as $index => $stage) {
            if ($stage->id === null) {
                $row = $taskTemplate->stages()->create(['name' => $stage->name, 'sort_order' => $index]);
                $keptIds[] = $row->id;
                $idsByKey[$stage->key] = $row->id;

                continue;
            }

            /** @var TaskTemplateStage $row */
            $row = $existing->get($stage->id);
            $row->fill(['name' => $stage->name, 'sort_order' => $index]);
            $row->save();
            $keptIds[] = $row->id;
            $idsByKey[$stage->key] = $row->id;
        }

        $existing
            ->filter(static fn (TaskTemplateStage $row): bool => ! in_array($row->id, $keptIds, true))
            ->each(static fn (TaskTemplateStage $row) => $row->delete());

        return $idsByKey;
    }
}
