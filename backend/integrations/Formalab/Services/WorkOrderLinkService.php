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
 * The work order side of the integration: the link to the Formalab edition
 * is never chosen by hand, it is found from the course name (spec 0422 D-5).
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
     * "Sincronizza": finds the edition whose label equals the work order's
     * course name (gol_corso), re-reads it live from Formalab, writes the
     * course fields and records the link. Nothing is saved on failure.
     *
     * @throws ValidationException not eligible, no course name, no or several matching editions, edition gone, or a value refused by the fields
     * @throws FormalabException Formalab not reachable
     */
    public function sync(WorkOrder $workOrder, User $actor): FormalabWorkOrderLink
    {
        // Step 1: only work orders carrying the course fields can be synced
        if (! $this->isEligible($workOrder)) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.not_eligible')]);
        }

        // Step 2: the edition is the one whose label equals the course name
        $editionId = $this->matchEdition($workOrder);

        // Step 3: live read of the class (also refreshes the local catalogue)
        $class = $this->catalog->refresh($editionId, Trigger::WorkOrder);

        if ($class === null) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.class_not_found')]);
        }

        // Step 4: write the fields and record the link in one transaction
        DB::transaction(function () use ($workOrder, $class, $actor): void {
            $this->courseWriter->apply($workOrder, $class);

            $link = FormalabWorkOrderLink::query()->firstOrNew(['work_order_id' => $workOrder->id]);
            $link->fill([
                'edition_id' => $class->edition_id,
                'linked_by' => $link->linked_by ?? $actor->id,
                'synced_hash' => $class->payload_hash,
                'synced_at' => now(),
                'sync_error' => null,
            ])->save();
        });

        return $this->find($workOrder);
    }

    /**
     * @throws ValidationException empty course name, or no / several catalogue classes with that label
     */
    private function matchEdition(WorkOrder $workOrder): int
    {
        $courseName = trim((string) ($workOrder->attribute_values[CourseFieldWriter::ELIGIBILITY_CODE] ?? ''));

        if ($courseName === '') {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.course_name_missing')]);
        }

        $editionIds = $this->catalog->editionIdsForLabel($courseName);

        return match (count($editionIds)) {
            1 => $editionIds[0],
            0 => throw ValidationException::withMessages(['work_order' => __('formalab::messages.course_name_unmatched')]),
            default => throw ValidationException::withMessages(['work_order' => __('formalab::messages.course_name_ambiguous')]),
        };
    }
}
