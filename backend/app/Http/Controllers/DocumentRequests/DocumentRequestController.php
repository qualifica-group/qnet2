<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentRequests\StoreDocumentRequestRequest;
use App\Http\Requests\DocumentRequests\UpdateDocumentRequestRequest;
use App\Http\Resources\DocumentRequestResource;
use App\Http\Resources\DocumentRequestSyncLogResource;
use App\Models\DocumentRequest;
use App\Models\WorkOrder;
use App\Services\DocumentRequests\DocumentRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Document requests of a work order (spec 0428). Every action needs the
 * `document-requests.*` permission AND `view` on the work order (the policy
 * checks both for the per-record abilities).
 */
class DocumentRequestController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentRequestService $service) {}

    /**
     * GET /api/work-orders/{workOrder}/document-requests
     */
    public function index(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('viewAny', DocumentRequest::class);
            $this->authorize('view', $workOrder);

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => DocumentRequestResource::collection($this->service->listFor($workOrder))->resolve($request),
                'meta' => ['can_create' => (bool) $request->user()?->can('create', DocumentRequest::class)],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * GET /api/work-orders/{workOrder}/document-requests/defaults
     */
    public function defaults(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('create', DocumentRequest::class);
            $this->authorize('view', $workOrder);

            return $this->ok($this->service->defaults($workOrder));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/document-requests
     */
    public function store(StoreDocumentRequestRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('create', DocumentRequest::class);
            $this->authorize('view', $workOrder);

            $created = $this->service->create($workOrder, $request->user(), $request->checklist(), $request->requestData(), $request->shouldSend());

            return $this->created(new DocumentRequestResource($created));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * GET /api/document-requests/{documentRequest}
     */
    public function show(DocumentRequest $documentRequest): JsonResponse
    {
        try {
            $this->authorize('view', $documentRequest);

            return $this->ok(new DocumentRequestResource($documentRequest));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentRequest' => $documentRequest->id]);
        }
    }

    /**
     * PUT /api/document-requests/{documentRequest}
     */
    public function update(UpdateDocumentRequestRequest $request, DocumentRequest $documentRequest): JsonResponse
    {
        try {
            $this->authorize('update', $documentRequest);

            return $this->ok(new DocumentRequestResource(
                $this->service->update($documentRequest, $request->requestData(), $request->shouldSend()),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentRequest' => $documentRequest->id]);
        }
    }

    /**
     * GET /api/document-requests/{documentRequest}/sync-logs
     */
    public function logs(DocumentRequest $documentRequest): JsonResponse
    {
        try {
            $this->authorize('view', $documentRequest);

            return $this->ok(DocumentRequestSyncLogResource::collection($this->service->recentSyncLogs($documentRequest)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentRequest' => $documentRequest->id]);
        }
    }
}
