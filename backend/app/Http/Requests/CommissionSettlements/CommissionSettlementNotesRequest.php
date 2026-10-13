<?php

namespace App\Http\Requests\CommissionSettlements;

use App\Enums\CommissionSettlementStatus;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\CommissionSettlement;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/commission-settlements/{commissionSettlement} (spec 0213):
 * only the notes are writable, and only on a draft. The field-permission guard
 * runs for drafts only: outside DRAFT the Service answers 409, the state error
 * the contract promises, instead of a field-level 422.
 */
class CommissionSettlementNotesRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NOTES_MAX = 2000;

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
            'notes' => ['present', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->settlement()?->status === CommissionSettlementStatus::Draft) {
                $this->enforceFieldPermissions($validator);
            }
        });
    }

    public function notes(): ?string
    {
        $notes = $this->safe()->input('notes');

        return $notes === null || $notes === '' ? null : (string) $notes;
    }

    protected function authorizationResource(): string
    {
        return 'commission-settlements';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->settlement();
    }

    private function settlement(): ?CommissionSettlement
    {
        $settlement = $this->route('commissionSettlement');

        return $settlement instanceof CommissionSettlement ? $settlement : null;
    }
}
