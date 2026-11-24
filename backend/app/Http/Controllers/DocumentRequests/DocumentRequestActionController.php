<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Services\DocumentRequests\DocumentRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Reminder, cancel and refresh of a document request (spec 0428): all writes
 * on the request (`document-requests.update`) that only queue the ArchivioSmart call.
 */
class DocumentRequestActionController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentRequestService $service) {}

    /**
     * POST /api/document-requests/{documentRequest}/remind
     */
    public function remind(DocumentRequest $documentRequest): JsonResponse
    {
        return $this->act($documentRequest, $this->service->remind(...), __FUNCTION__);
    }

    /**
     * POST /api/document-requests/{documentRequest}/cancel
     */
    public function cancel(DocumentRequest $documentRequest): JsonResponse
    {
        return $this->act($documentRequest, $this->service->cancel(...), __FUNCTION__);
    }

    /**
     * POST /api/document-requests/{documentRequest}/refresh
     */
    public function refresh(DocumentRequest $documentRequest): JsonResponse
    {
        return $this->act($documentRequest, $this->service->refresh(...), __FUNCTION__);
    }

    /**
     * @param  callable(DocumentRequest): DocumentRequest  $action
     */
    private function act(DocumentRequest $documentRequest, callable $action, string $method): JsonResponse
    {
        try {
            $this->authorize('update', $documentRequest);

            return $this->ok(new DocumentRequestResource($action($documentRequest)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, $method, ['documentRequest' => $documentRequest->id]);
        }
    }
}
