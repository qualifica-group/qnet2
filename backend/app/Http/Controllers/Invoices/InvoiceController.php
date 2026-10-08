<?php

namespace App\Http\Controllers\Invoices;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\InstallmentPreviewRequest;
use App\Http\Requests\Invoices\InvoiceDetailsRequest;
use App\Http\Requests\Invoices\InvoiceMonthlySummaryRequest;
use App\Http\Requests\Invoices\InvoiceWriteRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceMonthlySummary;
use App\Services\Invoices\InvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the `invoices` resource (spec 0194). Thin controller:
 * FormRequest validation, server-side authorization (InvoicePolicy), Service
 * call. The list itself is the generic `invoices` table domain.
 *
 * @see InvoiceService
 */
class InvoiceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InvoiceService $service,
        private readonly InvoiceMonthlySummary $summary,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * POST /api/invoices/installment-preview
     */
    public function installmentPreview(InstallmentPreviewRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();
            $payload = $request->payload();

            if (isset($payload['invoice_id'])) {
                $this->authorize('update', Invoice::query()->findOrFail($payload['invoice_id']));
            } elseif (! $actor->can('invoices.create') && ! $actor->can('invoices.update')) {
                throw new AuthorizationException;
            }

            return $this->ok($this->service->previewInstallments($payload));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/invoices/monthly-summary?year=&type=
     */
    public function monthlySummary(InvoiceMonthlySummaryRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Invoice::class);

            return $this->ok($this->summary->forYear($request->year(), $request->type()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/invoices/{invoice}
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->authorize('view', $invoice);

            return $this->detailResponse($request->user(), $this->service->detail($invoice));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoice' => $invoice->id]);
        }
    }

    /**
     * PUT /api/invoices/{invoice} (rebalances the open installments once some are collected)
     */
    public function update(InvoiceWriteRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->authorize('update', $invoice);

            return $this->detailResponse($request->user(), $this->service->update($invoice, $request->payload()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoice' => $invoice->id]);
        }
    }

    /**
     * PATCH /api/invoices/{invoice}/details
     */
    public function updateDetails(InvoiceDetailsRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->authorize('update', $invoice);

            return $this->detailResponse($request->user(), $this->service->updateDetails($invoice, $request->payload()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoice' => $invoice->id]);
        }
    }

    /**
     * DELETE /api/invoices/{invoice} (409 once an installment has collections)
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        try {
            $this->authorize('delete', $invoice);

            $this->service->delete($invoice);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoice' => $invoice->id]);
        }
    }

    private function detailResponse(User $actor, Invoice $invoice): JsonResponse
    {
        return $this->okWithPermissions(
            new InvoiceResource($invoice),
            $this->permissionsBuilder->build($this->authorization->resolve('invoices'), $actor, $invoice),
        );
    }
}
