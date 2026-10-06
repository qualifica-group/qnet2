<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailTemplateRenderController;
use App\Http\Requests\WorkOrderEmails\RenderWorkOrderEmailTemplateRequest;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/work-orders/{workOrder}/emails/render-template (spec 0175, D-4):
 * the Commessa binding of OwnerEmailTemplateRenderController. Invokable.
 */
class WorkOrderEmailTemplateRenderController extends OwnerEmailTemplateRenderController
{
    public function __invoke(RenderWorkOrderEmailTemplateRequest $request, WorkOrder $workOrder): JsonResponse
    {
        return $this->renderTemplate($request, $workOrder);
    }
}
