<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailController;
use App\Http\Requests\InvoiceEmails\StoreInvoiceEmailRequest;
use App\Http\Requests\WorkOrderEmails\UpdateOutboundEmailRequest;
use App\Http\Resources\OutboundEmailResource;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceEmailService;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET/POST/PATCH/DELETE `/api/invoices/{invoice}/emails[/{email}]` and
 * `.../send` (spec 0195, D-10/D-12): the invoice binding of
 * OwnerEmailController. Authorization: InvoicePolicy::viewEmails()/sendEmail().
 */
class InvoiceEmailController extends OwnerEmailController
{
    public function __construct(
        EmailOwnerRegistry $owners,
        OutboundEmailService $service,
        private readonly InvoiceEmailService $invoiceEmails,
    ) {
        parent::__construct($owners, $service);
    }

    public function index(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->listEmails($request, $invoice);
    }

    /**
     * Unlike the work order store, creates the draft with the generated PDF
     * attached unless `attach_pdf` is false.
     */
    public function store(StoreInvoiceEmailRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->authorizeSend($invoice);

            $email = $this->invoiceEmails->createDocumentDraft($invoice, $this->actor($request), $request->toData(), $request->attachPdf());

            return $this->created(new OutboundEmailResource($email));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'store', $invoice);
        }
    }

    public function show(Request $request, Invoice $invoice, int $email): JsonResponse
    {
        return $this->showEmail($request, $invoice, $email);
    }

    public function update(UpdateOutboundEmailRequest $request, Invoice $invoice, int $email): JsonResponse
    {
        return $this->updateEmail($request, $invoice, $email);
    }

    public function destroy(Request $request, Invoice $invoice, int $email): JsonResponse
    {
        return $this->destroyEmail($request, $invoice, $email);
    }

    public function send(Request $request, Invoice $invoice, int $email): JsonResponse
    {
        return $this->sendEmail($request, $invoice, $email);
    }
}
