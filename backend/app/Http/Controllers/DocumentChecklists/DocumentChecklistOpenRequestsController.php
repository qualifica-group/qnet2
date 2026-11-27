<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklists;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\DocumentChecklist;
use App\Services\DocumentRequests\DocumentRequestRulesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Open document requests of a checklist and the refresh of their rules after a
 * correction (spec 0432, D-1/D-2). Both need `update` on the checklist and the
 * `document-requests.update` permission: the rules belong to the checklist, so
 * every open request of it is reached, whatever its work order.
 */
class DocumentChecklistOpenRequestsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentRequestRulesService $rules) {}

    /**
     * GET /api/document-checklists/{documentChecklist}/open-requests
     */
    public function count(DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorizeRefresh($documentChecklist);

            return $this->ok(['count' => $this->rules->openRequestsCount($documentChecklist)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    /**
     * POST /api/document-checklists/{documentChecklist}/refresh-open-requests
     */
    public function refresh(Request $request, DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorizeRefresh($documentChecklist);

            return $this->ok(
                ['queued' => $this->rules->refreshOpenRequests($documentChecklist, $request->user())],
                'Accepted',
                HttpStatusEnum::ACCEPTED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    private function authorizeRefresh(DocumentChecklist $documentChecklist): void
    {
        $this->authorize('update', $documentChecklist);
        $this->authorize('document-requests.update');
    }
}
