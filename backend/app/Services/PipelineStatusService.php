<?php

namespace App\Services;

use App\DataObjects\PipelineStatuses\CreatePipelineStatusData;
use App\DataObjects\PipelineStatuses\UpdatePipelineStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\PipelineStatus;
use App\Services\Deletion\InUseGuard;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `pipeline-statuses` resource (spec 0023): a full-CRUD
 * lookup entity (name/color) shared by Projects and Campaigns. The
 * controller stays thin; this Service is the single authority, mirroring
 * SourceService's shape plus the BR-4 delete guard (ProductCategoryService's
 * abort(409) pattern).
 *
 * spec 0039: `sort_order` (D-5) is server-managed — placed by
 * StatusOrderManager::placeNew() on create, resequenced by reorder(); the two
 * mandatory system rows ("Nuovo"/"Chiuso", D-2) are protected by
 * SystemStatusGuard on both update() and delete().
 */
class PipelineStatusService
{
    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly InUseGuard $inUseGuard,
    ) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other status services even though `group`
     * is a plain column, needing no eager-load.
     */
    public function loadDetail(PipelineStatus $pipelineStatus): PipelineStatus
    {
        return $pipelineStatus;
    }

    public function create(CreatePipelineStatusData $data): PipelineStatus
    {
        $sortOrder = $this->orderManager->placeNew(PipelineStatus::class);

        $pipelineStatus = PipelineStatus::create([...$data->attributes(), 'sort_order' => $sortOrder]);

        return $this->loadDetail($pipelineStatus);
    }

    public function update(PipelineStatus $pipelineStatus, UpdatePipelineStatusData $data): PipelineStatus
    {
        $attributes = $data->submittedAttributes();

        $this->systemStatusGuard->assertUpdatable($pipelineStatus, $attributes);

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $pipelineStatus->fill($attributes)->save();

        return $this->loadDetail($pipelineStatus->fresh());
    }

    /**
     * BR-4 (status-delete-guard): a status referenced by at least one Project
     * or Campaign cannot be removed (it would silently orphan them). Defense
     * in depth: the FK is also restrictOnDelete at the schema layer. The
     * system-row guard (spec 0039, D-2) runs FIRST: a system row is never
     * deletable regardless of whether it happens to be unreferenced.
     */
    public function delete(PipelineStatus $pipelineStatus): void
    {
        $this->systemStatusGuard->assertDeletable($pipelineStatus);

        $this->inUseGuard->assertUnused($pipelineStatus);

        $pipelineStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list (spec 0039, D-5). See
     * StatusOrderManager::reorder() for the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, PipelineStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, PipelineStatus> $reordered */
        $reordered = $this->orderManager->reorder(PipelineStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated project status list for the for-select
     * standard (ADR 0011), mirroring SourceService::forSelect. Ordered by
     * `sort_order` first so the select mirrors the table's display order.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = PipelineStatus::query()->select(['id', 'name', 'system_key']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, PipelineStatus> $page */
        $page = $base->orderBy('sort_order')
            ->orderBy('name')
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, PipelineStatus>  $page
     * @return Collection<int, PipelineStatus>
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

        /** @var Collection<int, PipelineStatus> $hydrated */
        $hydrated = PipelineStatus::query()
            ->select(['id', 'name', 'system_key'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
