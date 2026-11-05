<?php

namespace App\Http\Requests\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\TreasuryType;
use App\Rules\ChartAccountRules;
use App\Rules\UniqueChartCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT/PATCH /api/treasury-types/{treasuryType} (spec 0360): the same
 * fields as the store request, all `sometimes`.
 */
class UpdateTreasuryTypeRequest extends FormRequest
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
        return [
            'chart_account_id' => ['sometimes', 'required', 'integer', ChartAccountRules::accountOfKind(ChartAccountKind::Financial)],
            'code' => ['sometimes', 'required', ...UniqueChartCode::rules($this->treasuryType())],
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreChartAccountRequest::NAME_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreChartAccountRequest::DESCRIPTION_MAX],
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
        return 'treasury-types';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->treasuryType();
    }

    private function treasuryType(): TreasuryType
    {
        /** @var TreasuryType $treasuryType */
        $treasuryType = $this->route('treasuryType');

        return $treasuryType;
    }
}
