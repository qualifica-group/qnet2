<?php

namespace App\Http\Requests\CostTypes;

use App\DataObjects\CostTypes\CreateCostTypeData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/cost-types (spec 0210). Authorization
 * stays in the controller (CostTypePolicy); EnforcesFieldPermissions rejects
 * any submitted field the actor cannot edit.
 */
class StoreCostTypeRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 100;

    public const int DESCRIPTION_MAX = 500;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('cost_types', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'cost-types';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): CreateCostTypeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateCostTypeData::fromValidated($validated);
    }
}
