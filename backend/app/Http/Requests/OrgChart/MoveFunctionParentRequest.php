<?php

namespace App\Http\Requests\OrgChart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/org-chart/functions/{businessFunction}/parent (spec 0273 D-4b).
 * `parent_id: null` makes the function a root. Authorization (base permission, before validation) is in authorize(); the field permission is checked by the move service.
 */
class MoveFunctionParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('businessFunction'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['present', 'nullable', 'integer', 'exists:business_functions,id'],
        ];
    }
}
