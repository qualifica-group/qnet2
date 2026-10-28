<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Exceptions\WorkOrders\WorkOrderClosedForProgressException;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Database\Eloquent\Collection;

/**
 * List/create/delete of a commessa's manual progress entries (spec 0321).
 * Ordering/monotonicity rules are validated in the FormRequest (D-6); the
 * closed-commessa veto (D-8) lives here.
 */
final class WorkOrderProgressEntryService
{
    /**
     * @return Collection<int, WorkOrderProgressEntry>
     */
    public function list(WorkOrder $workOrder): Collection
    {
        return $workOrder->progressEntries()->with('creator')->get();
    }

    public function create(WorkOrder $workOrder, int $percentage, string $recordedOn, User $actor): WorkOrderProgressEntry
    {
        $this->assertOpen($workOrder);

        $entry = $workOrder->progressEntries()->create([
            'percentage' => $percentage,
            'recorded_on' => $recordedOn,
            'created_by' => $actor->id,
        ]);

        return $entry->load('creator');
    }

    public function delete(WorkOrder $workOrder, WorkOrderProgressEntry $entry): void
    {
        $this->assertOpen($workOrder);

        $entry->delete();
    }

    private function assertOpen(WorkOrder $workOrder): void
    {
        if ($workOrder->is_force_closed) {
            throw new WorkOrderClosedForProgressException;
        }
    }
}
