<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplates;

use App\Enums\EmailTemplateModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/email-templates/variables (spec 0175,
 * D-4): `module` is the only input, required and constrained to the enum —
 * mirrors DocumentLayoutVariableRequest. `module()` hands the resolved enum
 * to EmailTemplateVariableController, which picks the catalogue of the
 * module's EmailOwner (spec 0195, D-10).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('email-templates.view')).
 */
class EmailTemplateVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller.
        return true;
    }

    public function module(): EmailTemplateModule
    {
        return EmailTemplateModule::from((string) $this->validated('module'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', Rule::enum(EmailTemplateModule::class)],
        ];
    }
}
