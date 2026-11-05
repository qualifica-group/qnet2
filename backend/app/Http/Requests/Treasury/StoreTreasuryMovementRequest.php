<?php

namespace App\Http\Requests\Treasury;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/treasury-movements (spec 0355). Authorization is the
 * controller's (TreasuryMovementPolicy::create).
 */
class StoreTreasuryMovementRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesTreasuryMovement;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->movementRules(partial: false);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateMovement($validator, null);
        });
    }

    protected function authorizationResource(): string
    {
        return 'treasury-movements';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }
}
