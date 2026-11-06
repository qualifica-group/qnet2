<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailController;
use App\Http\Requests\WorkOrderEmails\ScheduleOutboundEmailRequest;
use App\Http\Requests\WorkOrderEmails\StoreOutboundEmailRequest;
use App\Http\Requests\WorkOrderEmails\UpdateOutboundEmailRequest;
use App\Http\Resources\OutboundEmailResource;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailScheduler;
use App\Services\OutboundEmails\OutboundEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET/POST/PATCH/DELETE `/api/work-orders/{workOrder}/emails[/{email}]` and
 * `.../send` (spec 0175): the Commessa binding of OwnerEmailController (spec
 * 0195, D-10). Authorization: WorkOrderPolicy::viewEmails()/sendEmail().
 * Spec 0362 adds `schedule`/`unschedule` (recurring emails), only wired for
 * the Commessa.
 */
class WorkOrderEmailController extends OwnerEmailController
{
    public function __construct(
        EmailOwnerRegistry $owners,
        OutboundEmailService $service,
        private readonly OutboundEmailScheduler $scheduler,
    ) {
        parent::__construct($owners, $service);
    }

    public function index(Request $request, WorkOrder $workOrder): JsonResponse
    {
        return $this->listEmails($request, $workOrder);
    }

    public function store(StoreOutboundEmailRequest $request, WorkOrder $workOrder): JsonResponse
    {
        return $this->storeEmail($request, $workOrder);
    }

    public function show(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        return $this->showEmail($request, $workOrder, $email);
    }

    public function update(UpdateOutboundEmailRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        return $this->updateEmail($request, $workOrder, $email);
    }

    public function destroy(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        return $this->destroyEmail($request, $workOrder, $email);
    }

    public function send(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        return $this->sendEmail($request, $workOrder, $email);
    }

    public function schedule(ScheduleOutboundEmailRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($workOrder);

            $actor = $this->actor($request);
            $resolved = $this->service->resolveVisibleOrFail($workOrder, $email, $actor);

            return $this->ok(new OutboundEmailResource($this->scheduler->schedule($workOrder, $resolved, $actor, $request->recurrence())));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'schedule', $workOrder, ['email' => $email]);
        }
    }

    public function unschedule(Request $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($workOrder);

            $actor = $this->actor($request);
            $resolved = $this->service->resolveVisibleOrFail($workOrder, $email, $actor);

            return $this->ok(new OutboundEmailResource($this->scheduler->unschedule($workOrder, $resolved, $actor)));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'unschedule', $workOrder, ['email' => $email]);
        }
    }
}
