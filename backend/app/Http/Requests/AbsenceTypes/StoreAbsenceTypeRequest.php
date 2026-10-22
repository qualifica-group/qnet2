<?php

namespace App\Http\Requests\AbsenceTypes;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\AbsenceType;
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

    public const int NOTICE_MAX_DAYS = 365;

    public const int ANNUAL_DAYS_MAX = 366;

    private const array MODES = ['allows_full_day', 'allows_half_day', 'allows_hourly'];

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
            'allows_full_day' => ['sometimes', 'boolean'],
            'allows_half_day' => ['sometimes', 'boolean'],
            'allows_hourly' => ['sometimes', 'boolean'],
            'min_notice_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::NOTICE_MAX_DAYS],
            'color' => ['sometimes', 'string', Rule::in(AbsenceType::COLORS)],
            'default_annual_days' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:'.self::ANNUAL_DAYS_MAX, 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->enforceTypeRules($validator);
        });
    }

    /**
     * Cross-field rules (spec 0304 D-9/D-12) on the values the type will end up
     * with: at least one mode allowed, a default allowance only on a type that
     * deducts the balance.
     */
    private function enforceTypeRules(Validator $validator): void
    {
        $noneAllowed = collect(self::MODES)->every(fn (string $mode): bool => ! $this->effective($mode, true));

        if ($noneAllowed && collect(self::MODES)->contains(fn (string $mode): bool => $this->has($mode))) {
            $validator->errors()->add('allows_full_day', __('absence_types.no_mode_allowed'));
        }

        if ($this->input('default_annual_days') !== null && ! $this->effective('deducts_balance', false)) {
            $validator->errors()->add('default_annual_days', __('absence_types.default_needs_balance'));
        }
    }

    private function effective(string $attribute, bool $fallback): bool
    {
        return $this->has($attribute)
            ? $this->boolean($attribute)
            : (bool) ($this->currentType()?->{$attribute} ?? $fallback);
    }

    protected function currentType(): ?AbsenceType
    {
        return null;
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
