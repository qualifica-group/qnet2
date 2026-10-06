<?php

declare(strict_types=1);

namespace App\Http\Controllers\OwnerEmails;

use App\Enums\HttpStatusEnum;
use App\Http\Requests\WorkOrderEmails\RenderWorkOrderEmailTemplateRequest;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST `.../emails/render-template` (spec 0175, D-4; generalized by owner in
 * spec 0195, D-10). `email_template_id` existing is validated by the
 * FormRequest (422); active + `module` = the owner's template module is the
 * business rule checked here (422 `template_not_available`) -- never a 404,
 * matching the frozen data_contract ("422 modello inesistente/inattivo/di
 * altro module", ALL three cases are 422).
 */
abstract class OwnerEmailTemplateRenderController extends AbstractOwnerEmailController
{
    protected function renderTemplate(RenderWorkOrderEmailTemplateRequest $request, Model $owner): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $emailOwner = $this->emailOwner($owner);
            $template = EmailTemplate::query()->find($request->emailTemplateId());

            if ($template === null || ! $template->is_active || $template->module !== $emailOwner->templateModule()) {
                return $this->fail(__('outbound_emails.template_not_available'), HttpStatusEnum::UNPROCESSABLE_ENTITY->value);
            }

            return $this->ok($emailOwner->render((string) $template->subject, (string) $template->body, $owner, $this->actor($request)));
        } catch (Throwable $exception) {
            return $this->failed($exception, '__invoke', $owner);
        }
    }
}
