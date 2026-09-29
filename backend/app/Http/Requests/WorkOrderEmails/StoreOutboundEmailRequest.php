<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails;

use App\DataObjects\WorkOrderEmails\OutboundEmailDraftData;
use App\Http\Requests\WorkOrderEmails\Concerns\ValidatesRecipients;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/work-orders/{workOrder}/emails (spec
 * 0175, D-2, AC-008): every field optional — a bare `{}` creates an empty
 * draft (the composer's "Nuova email" button).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('sendEmail', $workOrder)).
 */
class StoreOutboundEmailRequest extends FormRequest
{
    use ValidatesRecipients;

    private const int SUBJECT_MAX = 255;

    public function authorize(): bool
    {
        // Authorization handled in the controller via WorkOrderPolicy.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeRecipientsForValidation();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->recipientRules(),
            'email_template_id' => ['sometimes', 'nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:'.self::SUBJECT_MAX],
            'body' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('rich_text.html_max')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertRecipientLimit($validator);
        });
    }

    public function toData(): OutboundEmailDraftData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return OutboundEmailDraftData::fromValidated($validated);
    }
}
