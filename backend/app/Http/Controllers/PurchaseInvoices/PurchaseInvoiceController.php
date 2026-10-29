<?php

namespace App\Http\Controllers\PurchaseInvoices;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoiceDetailsRequest;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoiceInstallmentPreviewRequest;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoiceWriteRequest;
use App\Http\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\PurchaseInvoices\PurchaseInvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Endpoints of the `purchase-invoices` resource (spec 0250). Thin controller:
 * FormRequest validation, server-side authorization (PurchaseInvoicePolicy),
 * Service call. The list is the generic `purchase-invoices` table domain.
 *
 * @see PurchaseInvoiceService
 */
class PurchaseInvoiceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseInvoiceService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/purchase-invoices/{purchaseInvoice}
     */
    public function show(Request $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseInvoice);

            return $this->detailResponse($request->user(), $this->service->detail($purchaseInvoice));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchase_invoice' => $purchaseInvoice->id]);
        }
    }

    /**
     * POST /api/purchase-invoices
     */
    public function store(PurchaseInvoiceWriteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PurchaseInvoice::class);

            return $this->detailResponse($request->user(), $this->service->create($request->user(), $request->payload()), HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/purchase-invoices/{purchaseInvoice} (409 on an invoice; rebalances the schedule once payments exist)
     */
    public function update(PurchaseInvoiceWriteRequest $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseInvoice);

            return $this->detailResponse($request->user(), $this->service->update($purchaseInvoice, $request->payload()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchase_invoice' => $purchaseInvoice->id]);
        }
    }

    /**
     * PATCH /api/purchase-invoices/{purchaseInvoice}/details
     */
    public function updateDetails(PurchaseInvoiceDetailsRequest $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseInvoice);

            return $this->detailResponse($request->user(), $this->service->updateDetails($purchaseInvoice, $request->payload(), $request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchase_invoice' => $purchaseInvoice->id]);
        }
    }

    /**
     * DELETE /api/purchase-invoices/{purchaseInvoice} (409 on an invoice or with payments)
     */
    public function destroy(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        try {
            $this->authorize('delete', $purchaseInvoice);

            $this->service->delete($purchaseInvoice);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchase_invoice' => $purchaseInvoice->id]);
        }
    }

    /**
     * POST /api/purchase-invoices/installment-preview
     */
    public function installmentPreview(PurchaseInvoiceInstallmentPreviewRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();
            $payload = $request->payload();

            if (isset($payload['purchase_invoice_id'])) {
                $this->authorize('update', PurchaseInvoice::query()->findOrFail($payload['purchase_invoice_id']));
            } else {
                Gate::allowIf($actor->can('purchase-invoices.create') || $actor->can('purchase-invoices.update'));
            }

            return $this->ok($this->service->previewInstallments($payload));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function detailResponse(User $actor, PurchaseInvoice $document, HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            new PurchaseInvoiceResource($document),
            $this->permissionsBuilder->build($this->authorization->resolve('purchase-invoices'), $actor, $document),
            status: $status,
        );
    }
}
