<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\DocumentForSelectRequest;
use App\Http\Resources\InvoiceForSelectResource;
use App\Services\Invoices\InvoiceForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/invoices/for-select — minimal, searchable, paginated list feeding
 * entity-backed selects and the AI assistant mentions (ADR 0011, spec 0347).
 * No gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31); the rows are
 * restricted inside the Service exactly like the module's index.
 *
 * @see InvoiceForSelectService::forSelect
 */
class InvoiceForSelectController extends BaseApiController
{
    public function __construct(private readonly InvoiceForSelectService $service) {}

    public function __invoke(DocumentForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                InvoiceForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
                hasMore: $result->hasMore,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
