<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ContractStatuses\CreateContractStatusData;
use App\DataObjects\ContractStatuses\UpdateContractStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\ContractStatus;
use App\Services\Contracts\ContractStatusDefaultManager;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `contract-statuses` resource (spec 0072): a
 * full-CRUD lookup entity (name/description/color/group) describing a
 * Contract's working state, combining RewardStatus' `description`/`is_active`
 * shape with DocumentLayout's exclusive `is_default` (BR-5, delegated to
 * ContractStatusDefaultManager) and QuoteStatus' `group` classification.
 *
 * `sort_order` is server-managed — placed by StatusOrderManager::placeNew()
 * on create, resequenced by reorder(); the four mandatory system rows
 * ("Da validare"/"Sospeso"/"Annullato"/"Disdetto", spec 0072 D-2) are
 * protected by SystemStatusGuard on both update() and delete(). `is_default`
 * is never mass-assigned directly — every write path resolves/validates it
 * through ContractStatusDefaultManager, inside the same DB::transaction as
 * the rest of the write, so the "exactly one default" invariant is never
 * observable as violated even transiently.
 */
class ContractStatusService
{
    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly ContractStatusDefaultManager $defaultManager,
    ) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other status services.
     */
    public function loadDetail(ContractStatus $contractStatus): ContractStatus
    {
        return $contractStatus;
    }

    public function create(CreateContractStatusData $data): ContractStatus
    {
        return DB::transaction(function () use ($data): ContractStatus {
            // Step 1: resolve the effective is_default (BR-5a/c) BEFORE insert.
            $isDefault = $this->defaultManager->resolveIsDefaultForCreate($data->isDefault, $data->isActive);

            // Step 2: server-managed sort_order placement.
            $sortOrder = $this->orderManager->placeNew(ContractStatus::class);

            // Step 3: persist the row with both resolved values.
            $contractStatus = ContractStatus::create([
                ...$data->attributes(),
                'sort_order' => $sortOrder,
                'is_default' => $isDefault,
            ]);

            // Step 4: BR-5b — unset the flag on any previous default.
            if ($isDefault) {
                $this->defaultManager->clearOtherDefaults($contractStatus);
            }

            return $this->loadDetail($contractStatus);
        });
    }

    public function update(ContractStatus $contractStatus, UpdateContractStatusData $data): ContractStatus
    {
        return DB::transaction(function () use ($contractStatus, $data): ContractStatus {
            $attributes = $data->submittedAttributes();

            $requestedIsActive = $data->isActiveSubmitted ? $data->isActive : null;
            $requestedIsDefault = $data->isDefaultSubmitted ? $data->isDefault : null;

            // Step 1: system-row guard — a system row accepts ONLY name/color
            // (spec 0072, D-2), checked before anything else. `is_default` is
            // submitted-but-excluded from $attributes (applied separately in
            // Step 4), so it is added back here explicitly: otherwise a
            // system row could be silently promoted to default, bypassing
            // the guard entirely.
            $guardAttributes = $attributes;
            if ($data->isDefaultSubmitted) {
                $guardAttributes['is_default'] = $data->isDefault;
            }
            $this->systemStatusGuard->assertUpdatable($contractStatus, $guardAttributes);

            // Step 2: BR-5c/d/e transition validity, against the CURRENTLY
            // persisted state, before writing anything.
            $this->defaultManager->assertUpdateTransitionValid($contractStatus, $requestedIsActive, $requestedIsDefault);

            // Step 3: persist every plain submitted attribute (never
            // is_default — applied separately below). Unconditional save:
            // fires the model's saved event even when nothing native
            // changed, so the HasCustomFields write pipeline (spec 0021)
            // still persists a custom-fields-only edit.
            $contractStatus->fill($attributes)->save();

            // Step 4: BR-5b — promote to default and clear every sibling,
            // only when the client explicitly requested it (already
            // validated above).
            if ($requestedIsDefault === true) {
                $contractStatus->forceFill(['is_default' => true])->save();
                $this->defaultManager->clearOtherDefaults($contractStatus);
            }

            return $this->loadDetail($contractStatus->fresh());
        });
    }

    /**
     * A status referenced by at least one Contract cannot be removed (it
     * would silently orphan them). Defense in depth: the FK is also
     * restrictOnDelete at the schema layer. The system-row guard (spec 0072,
     * D-2) runs FIRST: a system row is never deletable regardless of whether
     * it happens to be unreferenced.
     */
    public function delete(ContractStatus $contractStatus): void
    {
        $this->systemStatusGuard->assertDeletable($contractStatus);

        if ($contractStatus->contracts()->exists()) {
            abort(409, 'This contract status is used by a contract and cannot be deleted.');
        }

        $contractStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list. See StatusOrderManager::reorder() for
     * the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, ContractStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, ContractStatus> $reordered */
        $reordered = $this->orderManager->reorder(ContractStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated contract status list for the for-select
     * standard (ADR 0011). Only `is_active = true` rows are eligible,
     * ordered by `sort_order` first so the select mirrors the table's
     * display order — a deactivated status stays visible on contracts
     * already assigned to it, but is never (re-)selectable.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = ContractStatus::query()->select(['id', 'name', 'system_key'])->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, ContractStatus> $page */
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
     * `is_active` filter (a contract keeps showing its current status even
     * after it is deactivated), same id/name projection applies. Total is
     * unaffected.
     *
     * @param  Collection<int, ContractStatus>  $page
     * @return Collection<int, ContractStatus>
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

        /** @var Collection<int, ContractStatus> $hydrated */
        $hydrated = ContractStatus::query()
            ->select(['id', 'name', 'system_key'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
