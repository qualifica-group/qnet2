<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailAttachmentController;
use App\Http\Requests\WorkOrderEmails\ImportWorkOrderEmailAttachmentRequest;
use App\Http\Requests\WorkOrderEmails\StoreWorkOrderEmailAttachmentRequest;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `/api/work-orders/{workOrder}/emails/{email}/attachments/*` (spec 0175,
 * D-7/D-8): the Commessa binding of OwnerEmailAttachmentController (spec
 * 0195, D-10).
 */
class WorkOrderEmailAttachmentController extends OwnerEmailAttachmentController
{
    public function store(StoreWorkOrderEmailAttachmentRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        return $this->storeAttachment($request, $workOrder, $email);
    }

    public function import(ImportWorkOrderEmailAttachmentRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        return $this->importAttachments($request, $workOrder, $email);
    }

    public function destroy(Request $request, WorkOrder $workOrder, int $email, int $attachment): JsonResponse
    {
        return $this->destroyAttachment($request, $workOrder, $email, $attachment);
    }

    public function download(Request $request, WorkOrder $workOrder, int $email, int $attachment): StreamedResponse|JsonResponse
    {
        return $this->downloadAttachment($request, $workOrder, $email, $attachment);
    }
}
