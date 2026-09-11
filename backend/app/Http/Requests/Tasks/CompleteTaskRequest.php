<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\Enums\TaskStatusGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks/{task}/complete (spec 0116,
 * data_contract). Both fields optional: an absent/null `validation_status_id`
 * is the document's CASO 1 (the Task closes positively); a submitted one is
 * CASO 2 and must reference an ACTIVE status belonging to the `in_validation`
 * group, in ONE combined rule — the same shape as
 * ValidateContractRequest's `contract_status_id`/closed_won pairing.
 *
 * Authorization stays in the controller (TaskPolicy::complete).
 */
class CompleteTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'closure_feedback' => ['sometimes', 'nullable', 'string'],
            'validation_status_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('task_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('group', TaskStatusGroup::InValidation->value)
                ),
            ],
        ];
    }

    public function toData(): CompleteTaskData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CompleteTaskData::fromValidated($validated);
    }
}
