<?php

namespace App\Http\Requests\AbsenceTypes;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/absence-types (spec 0278 D-5).
 * Authorization stays in the controller (AbsenceTypePolicy).
 */
class StoreAbsenceTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('absence_types', 'name')],
            'deducts_balance' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
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
        return 'absence-types';
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
