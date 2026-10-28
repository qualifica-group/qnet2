<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderWorkflows;

use App\DataObjects\WorkOrderWorkflows\CreateWorkOrderWorkflowData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\WorkOrderWorkflows\Concerns\ValidatesWorkOrderWorkflowPayload;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/work-order-workflows (spec 0322). Authorization stays
 * in the controller. `criteria` is required (min:1); `statuses` is optional
 * and covers the custom rows plus, optionally, seeds for the system rows
 * tagged by `system_key`.
 */
class StoreWorkOrderWorkflowRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->criteriaRules(required: true),
            ...$this->statusesRules(allowIds: false),
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
        return null;
    }

    public function toData(): CreateWorkOrderWorkflowData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateWorkOrderWorkflowData::fromValidated($validated);
    }
}
