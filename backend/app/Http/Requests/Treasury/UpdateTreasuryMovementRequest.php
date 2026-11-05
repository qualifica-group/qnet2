<?php

namespace App\Http\Requests\Treasury;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\TreasuryMovement;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT/PATCH /api/treasury-movements/{treasuryMovement} (spec 0360): the
 * store rules, all `sometimes`, with the cross-field checks run on the stored
 * values overlaid by the submitted ones.
 */
class UpdateTreasuryMovementRequest extends FormRequest
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
        return $this->movementRules(partial: true);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateMovement($validator, $this->treasuryMovement());
        });
    }

    protected function authorizationResource(): string
    {
        return 'treasury-movements';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->treasuryMovement();
    }

    private function treasuryMovement(): TreasuryMovement
    {
        /** @var TreasuryMovement $movement */
        $movement = $this->route('treasuryMovement');

        return $movement;
    }
}
