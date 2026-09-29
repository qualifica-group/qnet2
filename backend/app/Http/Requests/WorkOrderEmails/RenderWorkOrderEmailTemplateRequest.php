<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST
 * /api/work-orders/{workOrder}/emails/render-template (spec 0175, D-4):
 * `email_template_id` must reference a REAL row — active-and-`work_orders`
 * is a business rule (422 `template_not_available`), checked by the
 * controller/service, not by `exists` here.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('sendEmail', $workOrder)).
 */
class RenderWorkOrderEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email_template_id' => ['required', 'integer', 'exists:email_templates,id'],
        ];
    }

    public function emailTemplateId(): int
    {
        return (int) $this->validated('email_template_id');
    }
}
