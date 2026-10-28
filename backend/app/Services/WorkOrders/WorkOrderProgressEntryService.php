<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Database\Eloquent\Collection;

/**
 * List/create/delete of a commessa's manual progress entries (spec 0321).
 * Like the legacy `avanzamento_commesse`, an entry stores the INCREMENT: the
 * new total typed by the user minus the SUM already reached (the "above the
 * total" rule is validated in the FormRequest). No date or closed-commessa rule.
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

    /**
     * @param  int  $total  the new total progress ("% Stato attuale"), above the current SUM
     */
    public function create(WorkOrder $workOrder, int $total, string $recordedOn, User $actor): WorkOrderProgressEntry
    {
        $current = (int) $workOrder->progressEntries()->reorder()->sum('percentage');

        $entry = $workOrder->progressEntries()->create([
            'percentage' => $total - $current,
            'recorded_on' => $recordedOn,
            'created_by' => $actor->id,
        ]);

        return $entry->load('creator');
    }

    public function delete(WorkOrderProgressEntry $entry): void
    {
        $entry->delete();
    }
}
