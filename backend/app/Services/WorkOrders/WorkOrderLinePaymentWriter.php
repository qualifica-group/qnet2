<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\WorkOrders\UpdateWorkOrderLinePaymentData;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Notifications\WorkOrderLineDeliverableNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Saves the payment data of one commessa line (spec 0201, D-3/D-11) or of
 * several at once (spec 0333): the record is created on the first save, only
 * the submitted keys change. When the status moves from a non-deliverable (or
 * empty) one to one that allows delivery, the commessa's supervisors and
 * participants are notified (D-4/D-13), never the actor, once per user: a bulk
 * save sends ONE notification listing every line that became deliverable.
 */
final class WorkOrderLinePaymentWriter
{
    public function handle(WorkOrder $workOrder, QuoteLine $line, UpdateWorkOrderLinePaymentData $data, User $actor): WorkOrderLinePayment
    {
        // Step 1: apply only the submitted keys
        [$payment, $becameDeliverable] = $this->apply($workOrder, $line, $data);

        // Step 2: tell the team when the line just became deliverable
        if ($becameDeliverable) {
            $this->notifyDeliverable($workOrder, collect([$line]), $payment, $actor);
        }

        return $payment;
    }

    /**
     * The same keys on every line, in one transaction (spec 0333, D-2/D-4).
     *
     * @param  Collection<int, QuoteLine>  $lines
     */
    public function handleMany(WorkOrder $workOrder, Collection $lines, UpdateWorkOrderLinePaymentData $data, User $actor): void
    {
        DB::transaction(function () use ($workOrder, $lines, $data, $actor): void {
            // Step 1: apply the keys line by line, keeping the lines that just became deliverable
            $deliverable = collect();
            $payment = null;

            foreach ($lines as $line) {
                [$saved, $becameDeliverable] = $this->apply($workOrder, $line, $data);

                if ($becameDeliverable) {
                    $deliverable->push($line);
                    $payment = $saved;
                }
            }

            // Step 2: one notification for all of them
            if ($payment !== null) {
                $this->notifyDeliverable($workOrder, $deliverable, $payment, $actor);
            }
        });
    }

    /**
     * @return array{0: WorkOrderLinePayment, 1: bool} the saved record and whether it just became deliverable
     */
    private function apply(WorkOrder $workOrder, QuoteLine $line, UpdateWorkOrderLinePaymentData $data): array
    {
        $payment = WorkOrderLinePayment::query()->firstOrNew([
            'work_order_id' => $workOrder->id,
            'quote_line_id' => $line->id,
        ]);
        $wasDeliverable = $this->isDeliverable($payment->work_order_payment_status_id);

        $payment->fill($data->attributes())->save();

        return [$payment, ! $wasDeliverable && $this->isDeliverable($payment->work_order_payment_status_id)];
    }

    private function isDeliverable(?int $statusId): bool
    {
        return $statusId !== null
            && WorkOrderPaymentStatus::query()->whereKey($statusId)->where('allows_delivery', true)->exists();
    }

    /**
     * @param  SupportCollection<int, QuoteLine>  $lines  every line that just became deliverable, all on the same status
     */
    private function notifyDeliverable(WorkOrder $workOrder, SupportCollection $lines, WorkOrderLinePayment $payment, User $actor): void
    {
        $recipients = $this->recipients($workOrder, $actor);

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new WorkOrderLineDeliverableNotification(
            workOrderId: $workOrder->id,
            workOrderLabel: "{$workOrder->code} - {$workOrder->title}",
            productName: Product::query()->whereIn('id', $lines->pluck('product_id'))->orderBy('name')->pluck('name')->implode(', '),
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
