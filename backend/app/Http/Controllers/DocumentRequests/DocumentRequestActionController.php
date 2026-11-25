<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentRequests\ApproveDocumentRequestItemRequest;
use App\Http\Requests\DocumentRequests\RejectDocumentRequestItemRequest;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Services\DocumentRequests\DocumentRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Reminder, cancel, refresh and early completion of a document request (spec 0428,
 * 0429): writes on the request (`document-requests.update`) that only queue the
 * ArchivioSmart call. Approving or rejecting an item needs `document-requests.review`.
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
     * POST /api/document-requests/{documentRequest}/complete
     */
    public function complete(DocumentRequest $documentRequest): JsonResponse
    {
        return $this->act($documentRequest, $this->service->complete(...), __FUNCTION__);
    }

    /**
     * POST /api/document-requests/{documentRequest}/items/{item}/approve
     */
    public function approveItem(ApproveDocumentRequestItemRequest $request, DocumentRequest $documentRequest, int $item): JsonResponse
    {
        try {
            return $this->ok(new DocumentRequestResource(
                $this->service->approveItem($documentRequest, $item, $request->note(), $request->user()),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentRequest' => $documentRequest->id]);
        }
    }

    /**
     * POST /api/document-requests/{documentRequest}/items/{item}/reject
     */
    public function rejectItem(RejectDocumentRequestItemRequest $request, DocumentRequest $documentRequest, int $item): JsonResponse
    {
        try {
            return $this->ok(new DocumentRequestResource(
                $this->service->rejectItem($documentRequest, $item, (string) $request->note(), $request->user()),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentRequest' => $documentRequest->id]);
        }
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
