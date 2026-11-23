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
 * The work order side of the integration: the edition is the one chosen in
 * the work order's class select, the attribute its category maps on `label`
 * (spec 0426); the match is the edition id only.
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
     * "Sincronizza": re-reads live from Formalab the edition chosen in the
     * work order's class select, records the link and writes the course
     * fields. Nothing is saved on failure.
     *
     * @throws ValidationException not eligible, no class chosen, edition gone, or a value refused by the fields
     * @throws FormalabException Formalab not reachable
     */
    public function sync(WorkOrder $workOrder, User $actor): FormalabWorkOrderLink
    {
        // Step 1: only work orders of a linked category mapping the class can be synced
        if (! $this->isEligible($workOrder)) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.not_eligible')]);
        }

        // Step 2: the edition is the one chosen in the class select
        $editionId = $this->courseWriter->editionIdOf($workOrder)
            ?? throw ValidationException::withMessages(['work_order' => __('formalab::messages.course_name_missing')]);

        // Step 3: live read of the class (also refreshes the local catalogue)
        $class = $this->catalog->refresh($editionId, Trigger::WorkOrder);

        if ($class === null) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.class_not_found')]);
        }

        // Step 4: record the link first (the work order save then finds it aligned), then write the fields
        DB::transaction(function () use ($workOrder, $class, $actor): void {
            $link = FormalabWorkOrderLink::query()->firstOrNew(['work_order_id' => $workOrder->id]);
            $link->fill(['edition_id' => $class->edition_id, 'linked_by' => $link->linked_by ?? $actor->id])->save();

            $this->courseWriter->apply($workOrder, $class);

            $link->update(['synced_hash' => $class->payload_hash, 'synced_at' => now(), 'sync_error' => null]);
        });

        return $this->find($workOrder);
    }
}
