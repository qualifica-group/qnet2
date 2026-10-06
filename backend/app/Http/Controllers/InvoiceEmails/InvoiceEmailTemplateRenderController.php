<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailTemplateRenderController;
use App\Http\Requests\WorkOrderEmails\RenderWorkOrderEmailTemplateRequest;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/invoices/{invoice}/emails/render-template (spec 0195, D-10): the
 * invoice binding of OwnerEmailTemplateRenderController.
 */
class InvoiceEmailTemplateRenderController extends OwnerEmailTemplateRenderController
{
    public function __invoke(RenderWorkOrderEmailTemplateRequest $request, Invoice $invoice): JsonResponse
    {
        return $this->renderTemplate($request, $invoice);
    }
}
