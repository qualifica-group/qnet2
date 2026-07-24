<?php

namespace App\Services;

use App\DataObjects\RewardStatuses\CreateRewardStatusData;
use App\DataObjects\RewardStatuses\UpdateRewardStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\RewardStatus;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `reward-statuses` resource (spec 0060): a full-CRUD
 * lookup entity (name/description/color/is_active) describing the STATE of
 * an assigned reward. Keeps the shared lookup-service shape (cloned from
 * OpportunityStatusService), including the BR-4 delete guard.
 *
 * `sort_order` is server-managed — placed by StatusOrderManager::placeNew()
 * on create, resequenced by reorder(); the ONE mandatory system row ("In
 * attesa"/`pending`, D-2) is protected by SystemStatusGuard on both update()
 * and delete().
 */
class RewardStatusService
{
    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
    ) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other status services.
     */
    public function loadDetail(RewardStatus $rewardStatus): RewardStatus
    {
        return $rewardStatus;
    }

    public function create(CreateRewardStatusData $data): RewardStatus
    {
        $sortOrder = $this->orderManager->placeNew(RewardStatus::class);

        $rewardStatus = RewardStatus::create([...$data->attributes(), 'sort_order' => $sortOrder]);

        return $this->loadDetail($rewardStatus);
    }

    public function update(RewardStatus $rewardStatus, UpdateRewardStatusData $data): RewardStatus
    {
        $attributes = $data->submittedAttributes();

        $this->systemStatusGuard->assertUpdatable($rewardStatus, $attributes);

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $rewardStatus->fill($attributes)->save();

        return $this->loadDetail($rewardStatus->fresh());
    }

    /**
     * BR-4 (delete-guard): a status referenced by at least one Reward cannot
     * be removed (it would silently orphan them). Defense in depth: the FK is
     * also restrictOnDelete at the schema layer. The system-row guard (spec
     * 0060, D-2) runs FIRST: the system row is never deletable regardless of
     * whether it happens to be unreferenced.
     */
    public function delete(RewardStatus $rewardStatus): void
    {
        $this->systemStatusGuard->assertDeletable($rewardStatus);

        if ($rewardStatus->rewards()->exists()) {
            abort(409, 'This reward status is used by a reward and cannot be deleted.');
        }

        $rewardStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list (D-3). See StatusOrderManager::reorder()
     * for the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, RewardStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, RewardStatus> $reordered */
        $reordered = $this->orderManager->reorder(RewardStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated reward status list for the for-select
     * standard (ADR 0011). BR-5: only `is_active = true` rows are eligible,
     * ordered by `sort_order` first so the select mirrors the table's display
     * order — a deactivated status stays visible on rewards already assigned
     * to it (D-7), but is never (re-)selectable.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = RewardStatus::query()->select(['id', 'name', 'system_key'])->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, RewardStatus> $page */
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
     * not already on the page, deduplicated. They bypass search AND the
     * `is_active` filter (D-7: a reward keeps showing its current status even
     * after it is deactivated), same id/name projection applies. Total is
     * unaffected.
     *
     * @param  Collection<int, RewardStatus>  $page
     * @return Collection<int, RewardStatus>
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

        /** @var Collection<int, RewardStatus> $hydrated */
        $hydrated = RewardStatus::query()
            ->select(['id', 'name', 'system_key'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
