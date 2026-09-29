<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplates;

use App\DataObjects\EmailTemplates\UpdateEmailTemplateData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\EmailTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/email-templates/{emailTemplate}
 * (spec 0175, D-14). Every field is `sometimes` to support partial PATCH
 * updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $emailTemplate)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted key the actor cannot edit
 * on this specific model.
 *
 * `module` is `prohibited` (data_contract: "module immutabile in update") —
 * the FIELD-PERMISSION ceiling in EmailTemplatesAuthorization also marks it
 * permanently readonly once persisted, but the 422 itself comes from here.
 */
class UpdateEmailTemplateRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int SUBJECT_MAX = 255;

    public function authorize(): bool
    {
        // Authorization handled in the controller via EmailTemplatePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $emailTemplate = $this->route('emailTemplate');
        $ignoreId = $emailTemplate instanceof EmailTemplate ? $emailTemplate->id : null;
        $module = $emailTemplate instanceof EmailTemplate ? $emailTemplate->module->value : null;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:'.self::NAME_MAX,
                Rule::unique('email_templates', 'name')->where('module', $module)->ignore($ignoreId),
            ],
            'module' => ['prohibited'],
            'subject' => ['sometimes', 'required', 'string', 'max:'.self::SUBJECT_MAX],
            'body' => ['sometimes', 'required', 'string', 'max:'.(int) config('rich_text.html_max')],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'email-templates';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $this->route('emailTemplate');

        return $emailTemplate;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateEmailTemplateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateEmailTemplateData::fromValidated($validated);
    }
}
