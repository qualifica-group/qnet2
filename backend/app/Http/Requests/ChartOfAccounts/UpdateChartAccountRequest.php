<?php

namespace App\Http\Requests\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\ChartAccount;
use App\Rules\UniqueChartCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT/PATCH /api/chart-accounts/{chartAccount} (spec 0348, D-9):
 * `level` never changes; an account can only move under a group of its own
 * kind; a group's kind is frozen once it has accounts.
 */
class UpdateChartAccountRequest extends FormRequest
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
        $chartAccount = $this->chartAccount();
        $isGroup = $chartAccount->level === ChartAccountLevel::Group;

        return [
            'level' => ['prohibited'],
            'parent_id' => $isGroup ? ['prohibited'] : $this->parentRules($chartAccount),
            'kind' => $isGroup ? ['sometimes', 'required', Rule::enum(ChartAccountKind::class)] : ['prohibited'],
            'code' => ['sometimes', 'required', ...UniqueChartCode::rules($chartAccount)],
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreChartAccountRequest::NAME_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreChartAccountRequest::DESCRIPTION_MAX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectKindChangeWithAccounts($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'chart-accounts';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->chartAccount();
    }

    /**
     * @return array<int, mixed>
     */
    private function parentRules(ChartAccount $account): array
    {
        $account->loadMissing('parent');

        return [
            'sometimes',
            'required',
            'integer',
            Rule::exists('chart_accounts', 'id')
                ->where('level', ChartAccountLevel::Group->value)
                ->where('kind', $account->effectiveKind()?->value),
        ];
    }

    private function rejectKindChangeWithAccounts(Validator $validator): void
    {
        $chartAccount = $this->chartAccount();

        if ($chartAccount->level !== ChartAccountLevel::Group
            || ! $this->has('kind')
            || $this->input('kind') === $chartAccount->kind?->value) {
            return;
        }

        if ($chartAccount->children()->exists()) {
            $validator->errors()->add('kind', __('chart_accounts.kind_locked'));
        }
    }

    private function chartAccount(): ChartAccount
    {
        /** @var ChartAccount $chartAccount */
        $chartAccount = $this->route('chartAccount');

        return $chartAccount;
    }
}
