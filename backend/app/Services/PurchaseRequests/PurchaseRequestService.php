<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\DataObjects\PurchaseRequests\PurchaseRequestData;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic of the purchase requests (spec 0208): create, edit (partial
 * since spec 0256: the lines sync only when sent), delete. Amounts are always computed here (D-12), never by the
 * client; a line is editable only while pending approval (D-10) and deletable
 * only while pending or rejected (D-11).
 */
class PurchaseRequestService
{
    /** Relations PurchaseRequestResource reads (lazy loading is forbidden). */
    public const array RESOURCE_RELATIONS = [
        'purchaseRequestPriority:id,name,color,icon',
        'requester:id,name',
        'functionManager:id,name',
        'customer:id,name',
        'workOrder:id,code,title',
        'company:id,denomination',
        'companySite:id,name',
        'operationalSite.addresses.city',
        'businessFunction:id,name',
        'createdBy:id,name',
        'closedBy:id,name',
        'lines.product:id,name',
        'lines.supplier:id,name',
        'lines.unitOfMeasure:id,name,symbol',
        'lines.vatRate:id,name,rate',
        'lines.approvedBy:id,name',
        'lines.purchaseOrderLine.purchaseOrder:id,number,year',
    ];

    /** Line states that block the deletion of the whole request (D-11). */
    private const array BLOCKING_STATUSES = [PurchaseRequestLineStatus::Ordered, PurchaseRequestLineStatus::Received];

    public function __construct(
        private readonly PurchaseRequestLineSynchronizer $lines,
        private readonly PurchaseRequestNotifier $notifier,
        private readonly PurchaseRequestClosureService $closure,
    ) {}

    public function create(PurchaseRequestData $data, User $actor): PurchaseRequest
    {
        $purchaseRequest = DB::transaction(function () use ($data, $actor): PurchaseRequest {
            // Step 1: header (created_by is never client input)
            $purchaseRequest = new PurchaseRequest($data->header);
            $purchaseRequest->forceFill(['created_by' => $actor->id])->save();

            // Step 2: lines, all born pending approval, then the totals
            $this->lines->createAll($purchaseRequest, $data->lines ?? []);

            return $purchaseRequest;
        });

        // Step 3: tell the function manager once the request is committed
        $this->notifier->created($purchaseRequest, $actor);

        return $this->detail($purchaseRequest);
    }

    public function update(PurchaseRequest $purchaseRequest, PurchaseRequestData $data, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $data, $actor): PurchaseRequest {
            // Step 1: serialize on the request and refuse a closed one
            $locked = PurchaseRequest::query()->whereKey($purchaseRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            // Step 2: header, then the line sync when the lines were sent (spec 0256, D-2)
            $locked->fill($data->header)->save();

            if ($data->lines !== null) {
                $this->lines->sync($locked, $actor, $data->lines);
            }

            // Step 3: closed by itself when no line is left to work on (D-7)
            $this->closure->closeIfComplete($locked, $actor);

            return $this->detail($locked);
        });
    }

    /** Delete the request, its lines, their logs and attachments (409 once something was ordered or received). */
    public function delete(PurchaseRequest $purchaseRequest): void
    {
        DB::transaction(function () use ($purchaseRequest): void {
            $locked = PurchaseRequest::query()->whereKey($purchaseRequest->getKey())->lockForUpdate()->firstOrFail();
            $lines = $locked->lines()->lockForUpdate()->get();

            if ($lines->contains(fn (PurchaseRequestLine $line): bool => in_array($line->status, self::BLOCKING_STATUSES, true))) {
                abort(409, 'This purchase request has ordered or received lines and cannot be deleted.');
            }

            // One by one: the DB cascade would skip the attachment cleanup of the models
            $lines->each(fn (PurchaseRequestLine $line) => $line->delete());
            $locked->delete();
        });
    }

    public function detail(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        return $purchaseRequest->fresh(self::RESOURCE_RELATIONS);
    }

    public function assertOpen(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->isClosed()) {
            abort(409, 'This purchase request is closed and can no longer be changed.');
        }
    }
}
