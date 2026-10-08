<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\UpdateInvoiceInstallmentRequest;
use App\Http\Resources\InvoiceInstallmentResource;
use App\Models\InvoiceInstallment;
use App\Services\Invoices\InvoiceInstallmentUpdater;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Show and edit one installment (spec 0197). The list is the generic
 * `invoice-installments` table domain; the collection stays on
 * InvoiceCollectionController.
 *
 * @see InvoiceInstallmentUpdater
 */
class InvoiceInstallmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly InvoiceInstallmentUpdater $updater) {}

    /**
     * GET /api/invoice-installments/{installment}
     */
    public function show(InvoiceInstallment $installment): JsonResponse
    {
        try {
            $this->authorize('view', $installment);

            return $this->ok(new InvoiceInstallmentResource($installment->load(InvoiceInstallmentResource::RELATIONS)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }

    /**
     * PATCH /api/invoice-installments/{installment}
     */
    public function update(UpdateInvoiceInstallmentRequest $request, InvoiceInstallment $installment): JsonResponse
    {
        try {
            $updated = $this->updater->handle($installment, $request->toData());

            return $this->ok(new InvoiceInstallmentResource($updated), 'Installment updated.');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }
}
