<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\UpdateInvoiceLineRevenueTypeRequest;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\ChartOfAccounts\RevenueTypeRef;
use App\Services\Invoices\InvoiceLineRevenueTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * PATCH /api/invoices/{invoice}/lines/{line}/revenue-type (spec 0350,
 * D-8): classify one line, on any document state. Gated by `invoices.update`
 * (InvoicePolicy, in the FormRequest), not by the state of the document. The route scopes
 * the binding, so a line of another invoice is a 404.
 */
class InvoiceLineRevenueTypeController extends BaseApiController
{
    public function __construct(private readonly InvoiceLineRevenueTypeService $service) {}

    public function __invoke(UpdateInvoiceLineRevenueTypeRequest $request, Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        try {
            $updated = $this->service->assign($invoice, $line, $request->revenueTypeId(), $request->user());

            return $this->ok([
                'id' => $updated->id,
                'revenue_type_id' => $updated->revenue_type_id,
                'revenue_type' => $updated->revenueType === null ? null : RevenueTypeRef::from($updated->revenueType),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoice' => $invoice->id, 'line' => $line->id]);
        }
    }
}
