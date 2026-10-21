<?php

namespace App\Http\Requests\ContractTypes;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/contract-types/{contractType}
 * (spec 0290 D-1). Every field is `sometimes` to support partial updates.
 */
class UpdateContractTypeRequest extends StoreContractTypeRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('contract_types', 'name')->ignore($this->contractType()->id)],
            ...$this->sharedRules(),
        ];
    }

    protected function authorizationModel(): ?Model
    {
        return $this->contractType();
    }

    private function contractType(): ContractType
    {
        /** @var ContractType $contractType */
        $contractType = $this->route('contractType');

        return $contractType;
    }
}
