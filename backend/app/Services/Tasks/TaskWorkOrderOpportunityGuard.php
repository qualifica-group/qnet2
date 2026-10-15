<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D-11 of spec 0154: `work_order_id` and `opportunity_id` are MUTUALLY
 * EXCLUSIVE on a Task (choosing one clears the other on the client;
 * submitting both is a 422 on the server, never silently resolved), and
 * choosing a commessa IMPOSES its own client onto `registry_id` — a
 * Commessa has no `registry_id` of its own, only the chain
 * `WorkOrder -> Quote -> Opportunity.registry_id` (the same chain
 * `WorkOrderService::forSelect`'s own `meta.registry` reads). A multi-offer
 * commessa (spec 0267) has several clients: none is imposed, the submitted one
 * must be among them.
 *
 * `resolveRegistryId()` does double duty, the same way
 * TaskReferentRegistryGuard's `assertBelongs()` reads a resulting pair
 * rather than a submitted one: a `registry_id` left empty is FILLED from the
 * commessa's own chain; a `registry_id` submitted alongside a commessa is
 * instead VALIDATED against it (422 on a mismatch) — an explicit client
 * choice that disagrees with the commessa is refused, never overwritten.
 *
 * Invoked by App\Services\TaskService inside the write transaction, on
 * create with the submitted payload and on update with the Task's
 * RESULTING state (after fill()), unconditionally — same convention as
 * TaskReferentRegistryGuard/TaskLeadRegistryGuard, so an untouched pair
 * that predates the rule is still re-checked and self-heals rather than
 * silently drifting.
 */
final class TaskWorkOrderOpportunityGuard
{
    /**
     * @throws ValidationException 422 on `work_order_id`
     */
    public function assertExclusive(?int $workOrderId, ?int $opportunityId): void
    {
        if ($workOrderId === null || $opportunityId === null) {
            return;
        }

        throw ValidationException::withMessages([
            'work_order_id' => ['A task cannot be linked to both a commessa and an opportunity.'],
        ]);
    }

    /**
     * @throws ValidationException 422 on `registry_id`
     */
    public function resolveRegistryId(?int $registryId, ?int $workOrderId): ?int
    {
        if ($workOrderId === null) {
            return $registryId;
        }

        $workOrder = WorkOrder::query()->with('quote.opportunity:id,registry_id')->find($workOrderId);

        if ($workOrder?->isMultiQuote()) {
            return $this->assertSourceRegistry($registryId, $workOrder);
        }

        $workOrderRegistryId = $workOrder?->quote?->opportunity?->registry_id;

        if ($registryId === null) {
            return $workOrderRegistryId;
        }

        if ($workOrderRegistryId !== null && $workOrderRegistryId !== $registryId) {
            throw ValidationException::withMessages([
                'registry_id' => ["The selected registry does not match the commessa's own client."],
            ]);
        }

        return $registryId;
    }

    /**
     * Spec 0267, D-11: a multi-offer commessa has no single client, so nothing
     * is imposed; a submitted client must be one of the source offers' clients.
     *
     * @throws ValidationException 422 on `registry_id`
     */
    private function assertSourceRegistry(?int $registryId, WorkOrder $workOrder): ?int
    {
        if ($registryId === null) {
            return null;
        }

        $belongs = DB::table('quotes')
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->whereIn('quotes.id', $workOrder->sourceQuoteIds())
            ->where('opportunities.registry_id', $registryId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'registry_id' => ["The selected registry is not a client of the commessa's offers."],
            ]);
        }

        return $registryId;
    }
}
