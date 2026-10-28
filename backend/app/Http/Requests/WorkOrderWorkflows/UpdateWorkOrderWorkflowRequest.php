<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderWorkflows;

use App\DataObjects\WorkOrderWorkflows\UpdateWorkOrderWorkflowData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\WorkOrderWorkflows\Concerns\ValidatesWorkOrderWorkflowPayload;
use App\Models\WorkOrderWorkflow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT/PATCH /api/work-order-workflows/{workOrderWorkflow} (spec
 * 0322). Every field is `sometimes` (partial PATCH); `criteria`, when
 * submitted, is a full-replace sync (min:1); `statuses`, when submitted,
 * syncs the custom rows (`statuses.*.id` present = update, absent = new) and
 * the descriptive fields of the system rows. Authorization stays in the
 * controller.
 */
class UpdateWorkOrderWorkflowRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesWorkOrderWorkflowPayload;

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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->criteriaRules(required: false),
            ...$this->statusesRules(allowIds: true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateCriteria($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'work-order-workflows';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var WorkOrderWorkflow $workOrderWorkflow */
        $workOrderWorkflow = $this->route('workOrderWorkflow');

        return $workOrderWorkflow;
    }

    public function toData(): UpdateWorkOrderWorkflowData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateWorkOrderWorkflowData::fromValidated($validated);
    }
}
