<?php

namespace App\Services;

use App\DataObjects\RewardTypes\CreateRewardTypeData;
use App\DataObjects\RewardTypes\UpdateRewardTypeData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Reward;
use App\Models\RewardType;
use Illuminate\Support\Collection;

/**
 * Business logic for the `reward-types` resource (spec 0058): a full-CRUD
 * lookup entity (name/color) describing the TYPES of voucher/reward/
 * incentive usable in the CRM. Unlike its template
 * `OpportunityStatusService`, there is no server-managed `sort_order` and no
 * system-row guard: every row is a plain, user-owned custom row.
 */
class RewardTypeService
{
    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other lookup services even though a `RewardType` has no
     * relation to eager-load.
     */
    public function loadDetail(RewardType $rewardType): RewardType
    {
        return $rewardType;
    }

    public function create(CreateRewardTypeData $data): RewardType
    {
        $rewardType = RewardType::create($data->attributes());

        return $this->loadDetail($rewardType);
    }

    public function update(RewardType $rewardType, UpdateRewardTypeData $data): RewardType
    {
        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $rewardType->fill($data->submittedAttributes())->save();

        return $this->loadDetail($rewardType->fresh());
    }

    /**
     * Restrictive delete (spec 0059, AC-003): `rewards.reward_type_id` is the
     * FIRST FK ever to reference `reward_types` (BR-3 no longer holds) — a
     * type still assigned to at least one reward cannot be removed, mirroring
     * ReferentService::delete()'s own 409 guard. Queried directly against
     * `Reward` (no inverse relation added to RewardType — out of this
     * service's write surface) rather than via a relation method.
     */
    public function delete(RewardType $rewardType): void
    {
        $isReferenced = Reward::query()->where('reward_type_id', $rewardType->id)->exists();

        abort_if($isReferenced, 409, 'This reward type is assigned to at least one reward and cannot be deleted.');

        $rewardType->delete();
    }

    /**
     * Minimal, searchable, paginated reward type list for the for-select
     * standard (ADR 0011, spec 0058 D-7). Ordered by `name` asc — there is no
     * `sort_order` (BR-4).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = RewardType::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, RewardType> $page */
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, RewardType>  $page
     * @return Collection<int, RewardType>
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

        /** @var Collection<int, RewardType> $hydrated */
        $hydrated = RewardType::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
