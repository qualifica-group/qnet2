<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplates;

use App\DataObjects\EmailTemplates\CreateEmailTemplateData;
use App\Enums\EmailTemplateModule;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/email-templates (spec 0175, D-14).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', EmailTemplate::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null).
 *
 * `name` is unique WITHIN `module` (data_model: unique(module, name)).
 * `body` is capped at the shared rich-text HTML ceiling
 * (config('rich_text.html_max')) — sanitization itself happens in
 * EmailTemplateService (D-11), not here.
 */
class StoreEmailTemplateRequest extends FormRequest
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
        return [
            'name' => [
                'required', 'string', 'max:'.self::NAME_MAX,
                Rule::unique('email_templates', 'name')->where('module', $this->input('module')),
            ],
            'module' => ['required', Rule::enum(EmailTemplateModule::class)],
            'subject' => ['required', 'string', 'max:'.self::SUBJECT_MAX],
            'body' => ['required', 'string', 'max:'.(int) config('rich_text.html_max')],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateEmailTemplateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateEmailTemplateData::fromValidated($validated);
    }
}
