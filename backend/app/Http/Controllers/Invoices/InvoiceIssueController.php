<?php

namespace App\Http\Controllers\Invoices;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\InvoiceWriteRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\ProformaRequest;
use App\Services\Invoices\InvoiceDraftBuilder;
use App\Services\Invoices\InvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Issue flow of a proforma request (spec 0194): the modal draft and the
 * creation of the document. Both need `invoices.create` AND the right to view
 * the request.
 *
 * @see InvoiceService::create
 */
class InvoiceIssueController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InvoiceService $service,
        private readonly InvoiceDraftBuilder $draftBuilder,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/proforma-requests/{proformaRequest}/invoice-draft
     */
    public function draft(ProformaRequest $proformaRequest): JsonResponse
    {
        try {
            $this->authorizeIssue($proformaRequest);
            $this->service->assertNotInvoiced($proformaRequest);

            return $this->ok($this->draftBuilder->build($proformaRequest));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['proformaRequest' => $proformaRequest->id]);
        }
    }

    /**
     * POST /api/proforma-requests/{proformaRequest}/invoice
     */
    public function store(InvoiceWriteRequest $request, ProformaRequest $proformaRequest): JsonResponse
    {
        try {
            $this->authorizeIssue($proformaRequest);

            $invoice = $this->service->create($proformaRequest, $request->user(), $request->payload());

            return $this->created(new InvoiceResource($invoice));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['proformaRequest' => $proformaRequest->id]);
        }
    }

    private function authorizeIssue(ProformaRequest $proformaRequest): void
    {
        $this->authorize('create', Invoice::class);
        $this->authorize('view', $proformaRequest);
    }
}
