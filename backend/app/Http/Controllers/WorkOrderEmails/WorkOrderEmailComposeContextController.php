<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailComposeContextController;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/work-orders/{workOrder}/emails/compose-context (spec 0175,
 * D-5/D-6/D-7/D-9): the Commessa binding of OwnerEmailComposeContextController.
 * Invokable, single action.
 */
class WorkOrderEmailComposeContextController extends OwnerEmailComposeContextController
{
    public function __invoke(Request $request, WorkOrder $workOrder): JsonResponse
    {
        return $this->composeContext($request, $workOrder);
    }
}
