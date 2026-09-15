<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\TaskTemplates\CreateTaskTemplateData;
use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\DataObjects\TaskTemplates\UpdateTaskTemplateData;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Services\TaskTemplates\TaskTemplateDescriptionWriter;
use App\Services\TaskTemplates\TaskTemplateItemWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `task-templates` resource (spec 0124, D-1): the
 * "Modello di Task" header + its ordered rows, written together as ONE unit
 * (no dedicated row endpoint). The controller stays thin; this Service is
 * the single authority, mirroring ProductTypologyService.
 */
class TaskTemplateService
{
    public function __construct(
        private readonly TaskTemplateDescriptionWriter $descriptionWriter,
        private readonly TaskTemplateItemWriter $itemWriter,
    ) {}

    public function create(CreateTaskTemplateData $data, User $actor): TaskTemplate
    {
        return DB::transaction(function () use ($data, $actor): TaskTemplate {
            // description is NOT in attributes() (spec 0128, D-2/D-3): the
            // header needs an id before an inline image can become one of
            // its own attachments, so it is applied and saved separately.
            $taskTemplate = new TaskTemplate($data->attributes());
            $taskTemplate->save();

            $this->descriptionWriter->applyOnCreate($taskTemplate, $data->description, $actor, 'description');

            if ($taskTemplate->isDirty('description')) {
                $taskTemplate->save();
            }

            $this->itemWriter->create($taskTemplate, $data->items, $actor);

            return $this->loadDetail($taskTemplate);
        });
    }

    public function update(TaskTemplate $taskTemplate, UpdateTaskTemplateData $data, User $actor): TaskTemplate
    {
        return DB::transaction(function () use ($taskTemplate, $data, $actor): TaskTemplate {
            $taskTemplate->fill($data->submittedAttributes());

            // Rich text description (spec 0128, D-2/D-3/D-4), only when the
            // key was actually submitted.
            if ($data->descriptionSubmitted) {
                $this->descriptionWriter->applyOnUpdate($taskTemplate, $data->description, $actor, 'description');
            }

            // Unconditional save: fires the model's saved event even when no
            // native attribute changed, so the HasCustomFields write pipeline
            // (spec 0021) persists a custom-fields-only edit.
            $taskTemplate->save();

            if ($data->itemsSubmitted()) {
                /** @var array<int, TaskTemplateItemData> $items */
                $items = $data->items;
                $this->itemWriter->sync($taskTemplate, $items, $actor);
            }

            return $this->loadDetail($taskTemplate->fresh());
        });
    }

    /**
     * Restrictive delete (spec 0124, D-5): a template referenced by at least
     * one Commessa cannot be removed (AC-011) — never a cascade, so no
     * generated Task is ever touched. An UNREFERENCED template's rows are
     * removed through Eloquent's own `::delete()` (never a bulk/cascade
     * query), so HasAttachments' `deleting` hook fires and every row's files
     * are swept from disk too (AC-010) — the FK's own `cascadeOnDelete`
     * would silently skip that cleanup.
     */
    public function delete(TaskTemplate $taskTemplate): void
    {
        if ($taskTemplate->workOrders()->exists()) {
            abort(409, "Questo modello di task e' stato usato per generare commesse e non puo' essere eliminato. Disattivalo.");
        }

        DB::transaction(function () use ($taskTemplate): void {
            $taskTemplate->items()->get()->each(static fn (TaskTemplateItem $item) => $item->delete());
            $taskTemplate->delete();
        });
    }

    /**
     * Minimal, searchable, paginated template list for the for-select
     * standard (ADR 0011): only ACTIVE templates, except the explicitly
     * requested `ids[]` (edit-mode hydration of an already-picked, possibly
     * since-deactivated template), mirroring TaskStatusService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = TaskTemplate::query()->select(['id', 'name', 'description'])->withCount('items')->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, TaskTemplate> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * The full detail projection (items ordered, each with its optional
     * status and its own attachments).
     */
    private function loadDetail(TaskTemplate $taskTemplate): TaskTemplate
    {
        return $taskTemplate->load(['items.taskStatus', 'items.attachments']);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated — bypasses BOTH search and the
     * `is_active` filter, same projection applies. Total is unaffected.
     *
     * @param  Collection<int, TaskTemplate>  $page
     * @return Collection<int, TaskTemplate>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, TaskTemplate> $hydrated */
        $hydrated = TaskTemplate::query()
            ->select(['id', 'name', 'description'])
            ->withCount('items')
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
