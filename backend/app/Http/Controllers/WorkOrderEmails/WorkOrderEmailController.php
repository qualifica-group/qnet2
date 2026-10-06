<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailController;
use App\Http\Requests\WorkOrderEmails\StoreOutboundEmailRequest;
use App\Http\Requests\WorkOrderEmails\UpdateOutboundEmailRequest;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET/POST/PATCH/DELETE `/api/work-orders/{workOrder}/emails[/{email}]` and
 * `.../send` (spec 0175): the Commessa binding of OwnerEmailController (spec
 * 0195, D-10). Authorization: WorkOrderPolicy::viewEmails()/sendEmail().
 */
class WorkOrderEmailController extends OwnerEmailController
{
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
}
