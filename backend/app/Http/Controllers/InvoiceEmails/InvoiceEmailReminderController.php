<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceEmails;

use App\Http\Controllers\OwnerEmails\AbstractOwnerEmailController;
use App\Http\Requests\InvoiceEmails\InvoiceReminderRequest;
use App\Http\Resources\OutboundEmailResource;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceEmailService;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/invoices/{invoice}/emails/reminder (spec 0195, D-13): creates a
 * payment reminder draft (409 when no installment is overdue).
 */
class InvoiceEmailReminderController extends AbstractOwnerEmailController
{
    public function __construct(EmailOwnerRegistry $owners, private readonly InvoiceEmailService $invoiceEmails)
    {
        parent::__construct($owners);
    }

    public function __invoke(InvoiceReminderRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->authorizeSend($invoice);

            $email = $this->invoiceEmails->createReminderDraft($invoice, $this->actor($request), $request->emailTemplateId());

            return $this->created(new OutboundEmailResource($email));
        } catch (Throwable $exception) {
            return $this->failed($exception, '__invoke', $invoice);
        }
    }
}
