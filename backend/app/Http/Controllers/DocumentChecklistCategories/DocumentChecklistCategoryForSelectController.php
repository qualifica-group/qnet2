<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklistCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentChecklistCategories\DocumentChecklistCategoryForSelectRequest;
use App\Http\Resources\DocumentChecklistCategoryForSelectResource;
use App\Services\DocumentChecklistCategoryService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/document-checklist-categories/for-select: minimal, searchable,
 * paginated list of the non-deleted categories feeding the checklist form
 * (spec 0410, ADR 0011). No permission gate beyond `auth:sanctum`.
 */
class DocumentChecklistCategoryForSelectController extends BaseApiController
{
    public function __construct(private readonly DocumentChecklistCategoryService $service) {}

    public function __invoke(DocumentChecklistCategoryForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                DocumentChecklistCategoryForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
