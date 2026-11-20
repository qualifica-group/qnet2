<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderTasksAssignedNotification;
use App\Support\Notifications\RecordDetails;
use Illuminate\Support\Facades\DB;

/**
 * Tells each supervisor of a just-created commessa, ONCE, how many tasks its
 * Modello di Task assigned to them (WorkOrderTaskGenerator assigns every
 * generated task to every supervisor). Keeps the audience rule the
 * per-task TaskAssigned had: the actor never hears about their own write.
 */
final class GeneratedTasksNotifier
{
    /**
     * @param  array<int, int>  $supervisorIds
     */
    public function notify(WorkOrder $workOrder, int $taskCount, array $supervisorIds, User $actor): void
    {
        // Step 1: nothing generated, or nobody but the actor to tell.
        $recipientIds = array_values(array_diff($supervisorIds, [$actor->id]));

        if ($taskCount === 0 || $recipientIds === []) {
            return;
        }

        // Step 2: only once the commessa is durable — WorkOrderService::create()
        // is still inside its transaction here, and a rollback must not notify.
        DB::afterCommit(function () use ($workOrder, $taskCount, $recipientIds, $actor): void {
            $notification = new WorkOrderTasksAssignedNotification(
                workOrderId: $workOrder->id,
                workOrderLabel: RecordDetails::labelFor($workOrder),
                taskCount: $taskCount,
                actorName: $actor->name,
                workOrderDetails: RecordDetails::for($workOrder),
            );

            User::query()->whereIn('id', $recipientIds)->get()->each->notify($notification);
        });
    }
}
