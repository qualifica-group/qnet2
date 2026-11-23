<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklists;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\DocumentChecklistResource;
use App\Http\Resources\DocumentChecklistSyncLogResource;
use App\Models\DocumentChecklist;
use App\Services\DocumentChecklistCategoryService;
use App\Services\DocumentChecklistService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Manual ArchivioSmart sync and sync log of the `document-checklists` resource
 * (spec 0403). Asking for a sync is a write on the checklist (`update`);
 * reading the log is a read (`view`).
 */
class DocumentChecklistSyncController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentChecklistService $service,
        private readonly DocumentChecklistCategoryService $categoryService,
    ) {}

    /**
     * POST /api/document-checklists/{documentChecklist}/sync
     */
    public function sync(DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorize('update', $documentChecklist);

            return $this->ok(
                new DocumentChecklistResource($this->service->requestSync($documentChecklist)),
                'Accepted',
                HttpStatusEnum::ACCEPTED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    /**
     * POST /api/document-checklists/sync-all (categories, then checklists)
     */
    public function syncAll(): JsonResponse
    {
        try {
            $this->authorize('update', new DocumentChecklist);

            // Categories first: ArchivioSmart must know them before the checklists that use them (D-9).
            $queuedCategories = $this->categoryService->requestSyncAll();
            $queued = $this->service->requestSyncAll();

            return $this->ok(['queued' => $queued, 'queued_categories' => $queuedCategories], 'Accepted', HttpStatusEnum::ACCEPTED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/document-checklists/{documentChecklist}/sync-logs
     */
    public function logs(DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorize('view', $documentChecklist);

            return $this->ok(DocumentChecklistSyncLogResource::collection($this->service->recentSyncLogs($documentChecklist)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }
}
