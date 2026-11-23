<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklistCategories;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\DocumentChecklistCategoryResource;
use App\Http\Resources\DocumentChecklistSyncLogResource;
use App\Models\DocumentChecklistCategory;
use App\Services\DocumentChecklistCategoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Manual ArchivioSmart sync and sync log of the `document-checklist-categories`
 * resource (spec 0410). Asking for a sync is a write (`update`); reading the log
 * is a read (`view`). The log rows have the same shape as the checklists' ones.
 */
class DocumentChecklistCategorySyncController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentChecklistCategoryService $service) {}

    /**
     * POST /api/document-checklist-categories/{documentChecklistCategory}/sync
     */
    public function sync(DocumentChecklistCategory $documentChecklistCategory): JsonResponse
    {
        try {
            $this->authorize('update', $documentChecklistCategory);

            return $this->ok(
                new DocumentChecklistCategoryResource($this->service->requestSync($documentChecklistCategory)),
                'Accepted',
                HttpStatusEnum::ACCEPTED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklistCategory' => $documentChecklistCategory->id]);
        }
    }

    /**
     * GET /api/document-checklist-categories/{documentChecklistCategory}/sync-logs
     */
    public function logs(DocumentChecklistCategory $documentChecklistCategory): JsonResponse
    {
        try {
            $this->authorize('view', $documentChecklistCategory);

            return $this->ok(DocumentChecklistSyncLogResource::collection($this->service->recentSyncLogs($documentChecklistCategory)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklistCategory' => $documentChecklistCategory->id]);
        }
    }
}
