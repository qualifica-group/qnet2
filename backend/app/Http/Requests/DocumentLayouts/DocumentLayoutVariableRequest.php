<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use App\Enums\DocumentLayoutModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/document-layouts/variables (spec 0069):
 * `module` is the only input, required and constrained to the enum (AC-045).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', DocumentLayout::class)).
 */
class DocumentLayoutVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the DocumentLayoutPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', Rule::enum(DocumentLayoutModule::class)],
        ];
    }

    /**
     * The validated `module`, resolved to its enum case.
     */
    public function module(): DocumentLayoutModule
    {
        return DocumentLayoutModule::from((string) $this->validated('module'));
    }
}
