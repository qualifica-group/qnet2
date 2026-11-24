<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklists;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentChecklists\DocumentChecklistForSelectRequest;
use App\Http\Resources\DocumentChecklistForSelectResource;
use App\Models\DocumentRequest;
use App\Services\DocumentChecklistService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/document-checklists/for-select: the checklists a document request can
 * use (spec 0428, D-8). Needs `document-requests.create`.
 */
class DocumentChecklistForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentChecklistService $service) {}

    public function __invoke(DocumentChecklistForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', DocumentRequest::class);

            $result = $this->service->forSelect($request->toData(), $request->categoryId());

            return $this->paginatedResponse(
                DocumentChecklistForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
