<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use App\Enums\ContactTypeEnum;
use App\Enums\DocumentRequestDeliveryStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Jobs\SyncDocumentRequestJob;
use App\Models\DocumentChecklist;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestSyncLog;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrders\WorkOrderQuoteLinks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Document requests of a work order (spec 0428). Every write only records the
 * intent (`delivery_status` pending + `pending_operation`) and queues the job
 * after the commit; the remote state never changes here (D-5).
 */
class DocumentRequestService
{
    public function __construct(private readonly WorkOrderQuoteLinks $quoteLinks) {}

    /**
     * @return Collection<int, DocumentRequest>
     */
    public function listFor(WorkOrder $workOrder): Collection
    {
        return DocumentRequest::query()
            ->where('work_order_id', $workOrder->id)
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * Values pre-filling the creation dialog (D-7): the primary email of the
     * customer of the work order, or of its first offer when it has several.
     *
     * @return array{recipient_email: string|null, customer_name: string|null}
     */
    public function defaults(WorkOrder $workOrder): array
    {
        $quoteId = $this->quoteLinks->quoteIdsOf($workOrder)[0] ?? null;
        $registry = $quoteId === null
            ? null
            : Quote::query()->with('opportunity.registry.personalData')->find($quoteId)?->opportunity?->registry;

        return [
            'recipient_email' => $registry?->personalData?->primaryContact(ContactTypeEnum::Email)?->value,
            'customer_name' => $registry?->name,
        ];
    }

    /**
     * @param  array{due_date?: string|null, recipient_email?: string|null, message?: string|null}  $data
     */
    public function create(WorkOrder $workOrder, User $actor, DocumentChecklist $checklist, array $data, bool $send): DocumentRequest
    {
        $request = DocumentRequest::query()->create([
            'work_order_id' => $workOrder->id,
            'document_checklist_id' => $checklist->id,
            'checklist_code' => $checklist->code,
            'checklist_name' => $checklist->name,
            'category_name' => $checklist->loadMissing('category')->category?->name,
            'due_date' => $data['due_date'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'message' => $data['message'] ?? null,
            'delivery_status' => DocumentRequestDeliveryStatus::Pending,
            'pending_operation' => DocumentRequestOperation::Upsert,
            'created_by' => $actor->id,
        ]);

        $this->queue($request, DocumentRequestOperation::Upsert, $send);

        return $request;
    }

    /**
     * @param  array{due_date?: string|null, recipient_email?: string|null, message?: string|null}  $data
     */
    public function update(DocumentRequest $request, array $data, bool $send): DocumentRequest
    {
        $this->assertOpen($request);

        $request->fill([
            'due_date' => $data['due_date'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        return $this->request($request, DocumentRequestOperation::Upsert, $send);
    }

    public function remind(DocumentRequest $request): DocumentRequest
    {
        if (! $request->isRemindable()) {
            $this->refuse(__('archivio_smart.request_not_remindable'));
        }

        return $this->request($request, DocumentRequestOperation::Remind);
    }

    /**
     * Cancelling an already cancelled request is a no-op (idempotent, §4).
     */
    public function cancel(DocumentRequest $request): DocumentRequest
    {
        if ($request->status === DocumentRequestStatus::Cancelled) {
            return $request;
        }

        if (in_array($request->status, [DocumentRequestStatus::Completed, DocumentRequestStatus::Expired], true)) {
            $this->refuse(__('archivio_smart.request_not_cancellable'));
        }

        return $this->request($request, DocumentRequestOperation::Cancel);
    }

    public function refresh(DocumentRequest $request): DocumentRequest
    {
        return $this->request($request, DocumentRequestOperation::Refresh);
    }

    /**
     * @return Collection<int, DocumentRequestSyncLog>
     */
    public function recentSyncLogs(DocumentRequest $request): Collection
    {
        return $request->syncLogs()
            ->latest('id')
            ->limit((int) config('archivio-smart.sync_log_display_limit'))
            ->get();
    }

    /**
     * Records the intent and queues the job. Public: the webhook asks for a
     * refresh when an event arrives out of order.
     */
    public function request(DocumentRequest $request, DocumentRequestOperation $operation, bool $send = true): DocumentRequest
    {
        $request->forceFill([
            'delivery_status' => DocumentRequestDeliveryStatus::Pending,
            'pending_operation' => $operation,
            'delivery_error' => null,
        ])->save();

        $this->queue($request, $operation, $send);

        return $request;
    }

    private function queue(DocumentRequest $request, DocumentRequestOperation $operation, bool $send = true): void
    {
        SyncDocumentRequestJob::dispatch($request->id, $operation, $send)->afterCommit();
    }

    private function assertOpen(DocumentRequest $request): void
    {
        if ($request->isClosed()) {
            $this->refuse(__('archivio_smart.request_status_closed'));
        }
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
