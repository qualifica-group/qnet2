<?php

namespace App\Http\Requests\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\RevenueType;
use App\Rules\ChartAccountRules;
use App\Rules\UniqueChartCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT/PATCH /api/revenue-types/{revenueType} (spec 0348): the same
 * fields as the store request, all `sometimes`.
 */
class UpdateRevenueTypeRequest extends FormRequest
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
            'chart_account_id' => ['sometimes', 'required', 'integer', ChartAccountRules::accountOfKind(ChartAccountKind::Revenue)],
            'code' => ['sometimes', 'required', ...UniqueChartCode::rules($this->revenueType())],
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreChartAccountRequest::NAME_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreChartAccountRequest::DESCRIPTION_MAX],
            'is_active' => ['sometimes', 'boolean'],
            'is_institution_commission_default' => ['sometimes', 'boolean'],
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
        return 'revenue-types';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->revenueType();
    }

    private function revenueType(): RevenueType
    {
        /** @var RevenueType $revenueType */
        $revenueType = $this->route('revenueType');

        return $revenueType;
    }
}
