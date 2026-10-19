<?php

namespace App\Http\Requests\OrgChart;

use App\Rules\ActiveBusinessFunction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/org-chart/people/{user}/function (spec 0273 D-4c). Authorization (base permission, before validation) is in authorize(); the field permission is checked by the move service.
 */
class MovePersonFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_function_id' => ['present', 'nullable', 'integer', 'exists:business_functions,id'],
            // Spec 0280: a person is never moved INTO an inactive function.
            'to_function_id' => ['required', 'integer', new ActiveBusinessFunction, 'different:from_function_id'],
        ];
    }
}
