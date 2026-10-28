<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderProgressEntries\StoreWorkOrderProgressEntryRequest;
use App\Http\Resources\WorkOrderProgressEntryResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use App\Services\WorkOrders\WorkOrderProgressEntryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Manual progress entries of a commessa (spec 0321): list/create/delete,
 * gated on the OWNING commessa (`view` for the read, `update` for writes, D-3).
 * `Route::scopeBindings()` turns a `{progressEntry}` of another commessa into 404.
 */
class WorkOrderProgressEntryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderProgressEntryService $service) {}

    public function index(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);

            return $this->ok(WorkOrderProgressEntryResource::collection($this->service->list($workOrder)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    public function store(StoreWorkOrderProgressEntryRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $entry = $this->service->create($workOrder, $request->percentage(), $request->recordedOn(), $actor);

            return $this->created(new WorkOrderProgressEntryResource($entry));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    public function destroy(WorkOrder $workOrder, WorkOrderProgressEntry $progressEntry): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $this->service->delete($progressEntry);

            return $this->ok();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id, 'progressEntry' => $progressEntry->id]);
        }
    }
}
