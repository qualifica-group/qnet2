<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Enums\EmailTemplateModule;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderEmails\RenderWorkOrderEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\WorkOrderEmailVariableResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/work-orders/{workOrder}/emails/render-template (spec 0175, D-4).
 * `email_template_id` existing is validated by the FormRequest (422); active
 * + `module = work_orders` is the business rule checked here (422
 * `template_not_available`) — never a 404, matching the frozen data_contract
 * ("422 modello inesistente/inattivo/di altro module", ALL three cases are
 * 422).
 *
 * Invokable, single action.
 */
class WorkOrderEmailTemplateRenderController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderEmailVariableResolver $resolver) {}

    public function __invoke(RenderWorkOrderEmailTemplateRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $template = EmailTemplate::query()->find($request->emailTemplateId());

            if ($template === null || ! $template->is_active || $template->module !== EmailTemplateModule::WorkOrders) {
                return $this->fail(__('outbound_emails.template_not_available'), HttpStatusEnum::UNPROCESSABLE_ENTITY->value);
            }

            $rendered = $this->resolver->render((string) $template->subject, (string) $template->body, $workOrder, $actor);

            return $this->ok($rendered);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id]);
        }
    }
}
