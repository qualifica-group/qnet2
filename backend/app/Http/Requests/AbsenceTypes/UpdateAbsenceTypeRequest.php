<?php

namespace App\Http\Requests\AbsenceTypes;

use App\Models\AbsenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/absence-types/{absenceType}
 * (spec 0278 D-5). Every field is `sometimes` to support partial updates.
 */
class UpdateAbsenceTypeRequest extends StoreAbsenceTypeRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('absence_types', 'name')->ignore($this->absenceType()->id)],
            'deducts_balance' => ['sometimes', 'required', 'boolean'],
            'requires_approval' => ['sometimes', 'required', 'boolean'],
            ...$this->sharedRules(),
        ];
    }

    protected function authorizationModel(): ?Model
    {
        return $this->absenceType();
    }

    private function absenceType(): AbsenceType
    {
        /** @var AbsenceType $absenceType */
        $absenceType = $this->route('absenceType');

        return $absenceType;
    }
}
