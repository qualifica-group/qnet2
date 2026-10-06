<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailAttachmentController;
use App\Http\Requests\InvoiceEmails\ImportInvoiceEmailAttachmentRequest;
use App\Http\Requests\WorkOrderEmails\StoreWorkOrderEmailAttachmentRequest;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `/api/invoices/{invoice}/emails/{email}/attachments/*` (spec 0195, D-10):
 * the invoice binding of OwnerEmailAttachmentController.
 */
class InvoiceEmailAttachmentController extends OwnerEmailAttachmentController
{
    public function store(StoreWorkOrderEmailAttachmentRequest $request, Invoice $invoice, int $email): JsonResponse
    {
        return $this->storeAttachment($request, $invoice, $email);
    }

    public function import(ImportInvoiceEmailAttachmentRequest $request, Invoice $invoice, int $email): JsonResponse
    {
        return $this->importAttachments($request, $invoice, $email);
    }

    public function destroy(Request $request, Invoice $invoice, int $email, int $attachment): JsonResponse
    {
        return $this->destroyAttachment($request, $invoice, $email, $attachment);
    }

    public function download(Request $request, Invoice $invoice, int $email, int $attachment): StreamedResponse|JsonResponse
    {
        return $this->downloadAttachment($request, $invoice, $email, $attachment);
    }
}
