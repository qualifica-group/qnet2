<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\WorkOrders\UpdateWorkOrderLinePaymentData;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Notifications\WorkOrderLineDeliverableNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves the payment data of ONE commessa line (spec 0201, D-3/D-11): the record
 * is created on the first save, only the submitted keys change. When the status
 * moves from a non-deliverable (or empty) one to one that allows delivery, the
 * commessa's supervisors and participants are notified (D-4/D-13), never the
 * actor, once per user.
 */
final class WorkOrderLinePaymentWriter
{
    public function handle(WorkOrder $workOrder, QuoteLine $line, UpdateWorkOrderLinePaymentData $data, User $actor): WorkOrderLinePayment
    {
        // Step 1: the existing record, or a new one for this line
        $payment = WorkOrderLinePayment::query()->firstOrNew([
            'work_order_id' => $workOrder->id,
            'quote_line_id' => $line->id,
        ]);
        $wasDeliverable = $this->isDeliverable($payment->work_order_payment_status_id);

        // Step 2: apply only the submitted keys
        $payment->fill($data->attributes())->save();

        // Step 3: tell the team when the line just became deliverable
        if (! $wasDeliverable && $this->isDeliverable($payment->work_order_payment_status_id)) {
            $this->notifyDeliverable($workOrder, $line, $payment, $actor);
        }

        return $payment;
    }

    private function isDeliverable(?int $statusId): bool
    {
        return $statusId !== null
            && WorkOrderPaymentStatus::query()->whereKey($statusId)->where('allows_delivery', true)->exists();
    }

    private function notifyDeliverable(WorkOrder $workOrder, QuoteLine $line, WorkOrderLinePayment $payment, User $actor): void
    {
        $recipients = $this->recipients($workOrder, $actor);

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new WorkOrderLineDeliverableNotification(
            workOrderId: $workOrder->id,
            workOrderLabel: "{$workOrder->code} - {$workOrder->title}",
            productName: (string) $line->product()->value('name'),
            statusName: (string) $payment->status()->value('name'),
            actorName: $actor->name,
        );

        // Only once the write is durable: a rolled-back save must not notify.
        DB::afterCommit(fn () => $recipients->each->notify($notification));
    }

    /**
     * Supervisors and participants, deduplicated, without the actor.
     *
     * @return Collection<int, User>
     */
    private function recipients(WorkOrder $workOrder, User $actor): Collection
    {
        $ids = $workOrder->supervisors()->pluck('users.id')
            ->merge($workOrder->participants()->pluck('users.id'))
            ->unique()
            ->reject(fn (int $id): bool => $id === $actor->id);

        return User::query()->whereIn('id', $ids)->get();
    }
}
