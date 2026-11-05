<?php

namespace App\Http\Requests\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Rules\UniqueChartCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/chart-accounts (spec 0348, D-9): a group has a kind and
 * no parent; an account has a group as parent and no kind (inherited).
 */
class StoreChartAccountRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 150;

    public const int DESCRIPTION_MAX = 500;

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
            'level' => ['required', Rule::enum(ChartAccountLevel::class)],
            'parent_id' => [
                'required_if:level,account',
                'prohibited_if:level,group',
                'integer',
                Rule::exists('chart_accounts', 'id')->where('level', ChartAccountLevel::Group->value),
            ],
            'kind' => ['required_if:level,group', 'prohibited_if:level,account', Rule::enum(ChartAccountKind::class)],
            'code' => ['required', ...UniqueChartCode::rules()],
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
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
        return 'chart-accounts';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }
}
