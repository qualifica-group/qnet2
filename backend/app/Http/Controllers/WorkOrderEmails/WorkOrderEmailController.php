<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderEmails\StoreOutboundEmailRequest;
use App\Http\Requests\WorkOrderEmails\UpdateOutboundEmailRequest;
use App\Http\Resources\OutboundEmailListItemResource;
use App\Http\Resources\OutboundEmailResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\OutboundEmailService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Index/store/show/update/destroy/send for a Commessa's OutboundEmail
 * history+composer (spec 0175, data_contract "Email della commessa").
 * Thin controller: WorkOrder-level authorization (WorkOrderPolicy),
 * FormRequest validation, Service call, Resource output — every other rule
 * (draft visibility/ownership, status conflicts, send validation) lives in
 * OutboundEmailService.
 *
 * @see OutboundEmailService
 */
class WorkOrderEmailController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly OutboundEmailService $service) {}

    /**
     * GET /api/work-orders/{workOrder}/emails — a custom envelope (data +
     * meta) since `meta` sits alongside `data`, not nested under it
     * (data_contract), mirroring NoteController::index().
     */
    public function index(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('viewEmails', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $page = max(1, $request->integer('page', 1));
            $paginator = $this->service->listForWorkOrder($workOrder, $actor, $page);

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => OutboundEmailListItemResource::collection($paginator->items())->resolve($request),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id]);
        }
    }

    public function store(StoreOutboundEmailRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $email = $this->service->createDraft($workOrder, $actor, $request->toData());

            return $this->created(new OutboundEmailResource($email));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id]);
        }
    }

    public function show(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorize('viewEmails', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->service->resolveVisibleOrFail($workOrder, $email, $actor);

            return $this->ok(new OutboundEmailResource($resolved));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email]);
        }
    }

    public function update(UpdateOutboundEmailRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->service->resolveVisibleOrFail($workOrder, $email, $actor);
            $updated = $this->service->updateDraft($resolved, $request->toData());

            return $this->ok(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email]);
        }
    }

    public function destroy(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->service->resolveVisibleOrFail($workOrder, $email, $actor);
            $this->service->deleteDraft($resolved);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email]);
        }
    }

    public function send(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->service->resolveVisibleOrFail($workOrder, $email, $actor);
            $sent = $this->service->send($resolved, $actor);

            return $this->ok(new OutboundEmailResource($sent), 'Accepted', HttpStatusEnum::ACCEPTED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email]);
        }
    }
}
