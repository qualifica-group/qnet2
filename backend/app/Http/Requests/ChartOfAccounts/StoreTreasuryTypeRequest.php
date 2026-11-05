<?php

namespace App\Http\Requests\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Rules\ChartAccountRules;
use App\Rules\UniqueChartCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/treasury-types (spec 0355): the parent must be an
 * ACCOUNT whose group is of kind financial.
 */
class StoreTreasuryTypeRequest extends FormRequest
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
            'chart_account_id' => ['required', 'integer', ChartAccountRules::accountOfKind(ChartAccountKind::Financial)],
            'code' => ['required', ...UniqueChartCode::rules()],
            'name' => ['required', 'string', 'max:'.StoreChartAccountRequest::NAME_MAX],
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
        return null;
    }
}
