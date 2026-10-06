<?php

declare(strict_types=1);

namespace App\Http\Requests\InvoiceEmails;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/invoices/{invoice}/emails/reminder (spec 0195, D-13). Whether the
 * template is active and of the invoices module is a business rule checked by
 * InvoiceEmailService. Authorization stays in the controller.
 */
class InvoiceReminderRequest extends FormRequest
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
        return ['email_template_id' => ['sometimes', 'nullable', 'integer', 'exists:email_templates,id']];
    }

    public function emailTemplateId(): ?int
    {
        $id = $this->validated('email_template_id');

        return $id === null ? null : (int) $id;
    }
}
