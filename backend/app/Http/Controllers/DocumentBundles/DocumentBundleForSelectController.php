<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentBundles;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentBundles\DocumentBundleForSelectRequest;
use App\Http\Resources\DocumentBundleForSelectResource;
use App\Services\DocumentBundleService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/document-bundles/for-select — minimal, searchable, paginated
 * active-document-bundle list feeding the Commessa email composer's "Da
 * modello documenti" picker (spec 0175, ADR 0011).
 *
 * Thin invokable controller: validation (DocumentBundleForSelectRequest),
 * Service call, paginated response. No permission gate beyond `auth:sanctum`
 * (ADR 0011, amended 2026-07-31).
 *
 * @see DocumentBundleService::forSelect
 */
class DocumentBundleForSelectController extends BaseApiController
{
    public function __construct(private readonly DocumentBundleService $service) {}

    public function __invoke(DocumentBundleForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                DocumentBundleForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
