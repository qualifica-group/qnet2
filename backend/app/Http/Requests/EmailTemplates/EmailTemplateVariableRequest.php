<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplates;

use App\Enums\EmailTemplateModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/email-templates/variables (spec 0175,
 * D-4): `module` is the only input, required and constrained to the enum —
 * mirrors DocumentLayoutVariableRequest. No `module()` accessor: unlike
 * DocumentLayoutVariableController, EmailTemplateVariableController never
 * needs the resolved value — EmailTemplateModule has only ONE case today
 * (D-10), so WorkOrderEmailVariableCatalog is not yet module-dispatched;
 * `rules()` alone is enough to reject an out-of-enum value with a 422.
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
