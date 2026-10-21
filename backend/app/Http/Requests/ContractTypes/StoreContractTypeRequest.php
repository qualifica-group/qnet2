<?php

namespace App\Http\Requests\ContractTypes;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contract-types (spec 0290 D-1).
 * Authorization stays in the controller (ContractTypePolicy).
 */
class StoreContractTypeRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 100;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('contract_types', 'name')],
            ...$this->sharedRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
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
        return 'contract-types';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }
}
