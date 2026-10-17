<?php

namespace App\Http\Requests\OrgChart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/org-chart/people/{user}/manager (spec 0273 D-4a). Both keys are
 * required (null = "no manager on that side"). Authorization (base permission, before validation) is in authorize(); the field permission is checked by the move service.
 */
class MovePersonManagerRequest extends FormRequest
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
            'from_manager_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
            'to_manager_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
