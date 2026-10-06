<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\InvoiceCollectionRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\InvoiceInstallment;
use App\Services\Invoices\InvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Record / clear the collection of one installment (spec 0194, D-13), gated by
 * the `collect` ability of the parent invoice.
 *
 * @see InvoiceService::recordCollection
 */
class InvoiceCollectionController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly InvoiceService $service) {}

    /**
     * PUT /api/invoice-installments/{installment}/collection
     */
    public function store(InvoiceCollectionRequest $request, InvoiceInstallment $installment): JsonResponse
    {
        try {
            $this->authorizeCollect($installment);
            $payload = $request->payload();

            return $this->ok(new InvoiceResource($this->service->recordCollection($installment, $payload['collected_amount'], $payload['collected_at'])));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }

    /**
     * DELETE /api/invoice-installments/{installment}/collection
     */
    public function destroy(InvoiceInstallment $installment): JsonResponse
    {
        try {
            $this->authorizeCollect($installment);

            return $this->ok(new InvoiceResource($this->service->clearCollection($installment)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }

    private function authorizeCollect(InvoiceInstallment $installment): void
    {
        $installment->loadMissing('invoice');
        $this->authorize('collect', $installment->invoice);
    }
}
