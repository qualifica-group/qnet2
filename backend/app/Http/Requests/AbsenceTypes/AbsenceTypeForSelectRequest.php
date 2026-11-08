<?php

namespace App\Http\Requests\AbsenceTypes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/absence-types/for-select (ADR 0011): only `ids[]`, the saved types
 * to hydrate in edit mode. No gate beyond `auth:sanctum` (see the controller).
 */
class AbsenceTypeForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_map('intval', $this->validated('ids', []));
    }
}
