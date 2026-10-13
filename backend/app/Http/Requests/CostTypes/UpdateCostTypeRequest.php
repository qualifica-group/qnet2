<?php

namespace App\Http\Requests\CostTypes;

use App\DataObjects\CostTypes\UpdateCostTypeData;
use App\Enums\CostTypeNature;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\CostType;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/cost-types/{costType} (spec 0210).
 * Every field is `sometimes` to support partial updates.
 */
class UpdateCostTypeRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $costType = $this->route('costType');
        $ignoreId = $costType instanceof CostType ? $costType->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreCostTypeRequest::NAME_MAX, Rule::unique('cost_types', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreCostTypeRequest::DESCRIPTION_MAX],
            'nature' => ['sometimes', 'required', Rule::enum(CostTypeNature::class)],
            'color' => ['sometimes', 'required', 'string', Rule::in(BadgeTokens::colors())],
            'sort_order' => ['prohibited'],
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
        /** @var CostType $costType */
        $costType = $this->route('costType');

        return $costType;
    }

    public function toData(): UpdateCostTypeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCostTypeData::fromValidated($validated);
    }
}
