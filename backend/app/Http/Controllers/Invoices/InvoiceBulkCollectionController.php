<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\InvoiceBulkCollectionRequest;
use App\Services\Invoices\InvoiceBulkCollector;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Collect several installments of one customer at once (spec 0198), gated by
 * invoices.collect on the request and on every invoice involved.
 *
 * @see InvoiceBulkCollector
 */
class InvoiceBulkCollectionController extends BaseApiController
{
    public function __construct(private readonly InvoiceBulkCollector $collector) {}

    /**
     * POST /api/invoice-installments/collections
     */
    public function __invoke(InvoiceBulkCollectionRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->collector->handle($request->user(), $request->items(), $request->collectedAt()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
