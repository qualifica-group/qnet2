<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Support\Trigger;

/**
 * Set, replace and remove the Formalab edition a work order is linked to.
 */
final class WorkOrderLinkService
{
    public function __construct(
        private readonly ClassCatalog $catalog,
        private readonly CourseFieldWriter $courseWriter,
    ) {}

    public function find(WorkOrder $workOrder): ?FormalabWorkOrderLink
    {
        return FormalabWorkOrderLink::query()
            ->with(['formalabClass', 'linkedBy'])
            ->where('work_order_id', $workOrder->id)
            ->first();
    }

    public function isEligible(WorkOrder $workOrder): bool
    {
        return $this->courseWriter->isEligible($workOrder);
    }

    /**
     * Links the work order to the edition, re-reading the class live from
     * Formalab and writing the course fields. Nothing is saved on failure.
     *
     * @throws ValidationException not eligible, unknown edition, or a value refused by the fields
     * @throws FormalabException Formalab not reachable
     */
    public function set(WorkOrder $workOrder, int $editionId, User $actor): FormalabWorkOrderLink
    {
        // Step 1: only work orders carrying the course fields can be linked
        if (! $this->isEligible($workOrder)) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.not_eligible')]);
        }

        // Step 2: live read of the class (also refreshes the local catalogue)
        $class = $this->catalog->refresh($editionId, Trigger::WorkOrder);

        if ($class === null) {
            throw ValidationException::withMessages(['edition_id' => __('formalab::messages.class_not_found')]);
        }

        // Step 3: write the fields and replace the link in one transaction
        DB::transaction(function () use ($workOrder, $class, $actor): void {
            $this->courseWriter->apply($workOrder, $class);

            FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->delete();
            FormalabWorkOrderLink::query()->create([
                'work_order_id' => $workOrder->id,
                'edition_id' => $class->edition_id,
                'linked_by' => $actor->id,
                'synced_hash' => $class->payload_hash,
                'synced_at' => now(),
            ]);
        });

        return $this->find($workOrder);
    }

    /**
     * Re-reads the linked edition live and writes the course fields now,
     * without waiting for the periodic sync. The link itself is kept.
     *
     * @throws ValidationException not linked, edition gone from Formalab, or a value refused by the fields
     * @throws FormalabException Formalab not reachable
     */
    public function refresh(WorkOrder $workOrder): FormalabWorkOrderLink
    {
        // Step 1: only a linked work order has an edition to re-read
        $link = $this->find($workOrder);

        if ($link === null) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.not_linked')]);
        }

        // Step 2: live read of the class (also refreshes the local catalogue)
        $class = $this->catalog->refresh($link->edition_id, Trigger::WorkOrder);

        if ($class === null) {
            throw ValidationException::withMessages(['edition_id' => __('formalab::messages.class_not_found')]);
        }

        // Step 3: write the fields and mark the link as synced
        DB::transaction(function () use ($workOrder, $class, $link): void {
            $this->courseWriter->apply($workOrder, $class);
            $link->update(['synced_hash' => $class->payload_hash, 'synced_at' => now(), 'sync_error' => null]);
        });

        return $this->find($workOrder);
    }

    /**
     * Unlinks; the fields keep the values of the last sync.
     */
    public function remove(WorkOrder $workOrder): void
    {
        FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->delete();
    }
}
