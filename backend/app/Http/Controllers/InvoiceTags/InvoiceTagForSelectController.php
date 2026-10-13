<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceTags;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\InvoiceTags\InvoiceTagForSelectRequest;
use App\Http\Resources\InvoiceTagForSelectResource;
use App\Services\InvoiceTagService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/invoice-tags/for-select: minimal, searchable, paginated tag list
 * feeding entity-backed selects (ADR 0011). No gate beyond `auth:sanctum`: the
 * invoice form needs the list from actors without browse rights on the catalog.
 *
 * @see InvoiceTagService::forSelect
 */
class InvoiceTagForSelectController extends BaseApiController
{
    public function __construct(private readonly InvoiceTagService $service) {}

    public function __invoke(InvoiceTagForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                InvoiceTagForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
