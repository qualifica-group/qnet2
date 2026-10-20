<?php

namespace App\Http\Requests\EmploymentContracts;

use App\Authorization\UsersAuthorization;
use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Models\EmploymentContract;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/users/{user}/employment-contracts (spec 0272 D-1/D-4/D-5).
 * Authorization: `users.update` on the target user and the
 * `employment.contracts` field editable in the actor's matrix (spec 0283), and
 * compensation fields (`salary_*`) additionally need `users.manageCompensation`
 * (403 otherwise, before any payload validation).
 */
class StoreEmploymentContractRequest extends FormRequest
{
    private const int CHANGE_REASON_MAX = 255;

    private const string COMPENSATION_PERMISSION = 'users.manageCompensation';

    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');
        $actor = $this->user();

        if (! $actor?->can('update', $target)) {
            return false;
        }

        if (! app(UsersAuthorization::class)->fieldPermissions($actor, $target)[UsersAuthorization::CONTRACTS_FIELD]->editable) {
            return false;
        }

        $sendsSalary = $this->input('salary_amount') !== null || $this->input('salary_period') !== null;

        return ! $sendsSalary || $actor->can(self::COMPENSATION_PERMISSION);
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'starts_on' => [
                'required',
                'date_format:Y-m-d',
                $this->uniqueForProfile(...),
            ],
            'relationship_type' => ['required', Rule::enum(RelationshipTypeEnum::class)],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'collective_agreement_level_id' => ['nullable', 'integer', 'exists:collective_agreement_levels,id'],
            'salary_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'salary_period' => ['nullable', 'required_with:salary_amount', Rule::enum(SalaryPeriodEnum::class)],
            'change_reason' => ['nullable', 'string', 'max:'.self::CHANGE_REASON_MAX],
        ];
    }

    /**
     * One condition per start date and person. whereDate, not a unique rule:
     * SQLite keeps the cast date with a time part, so a plain equality misses.
     */
    private function uniqueForProfile(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var User $target */
        $target = $this->route('user');
        $profileId = $target->employment?->id;

        if ($profileId !== null
            && EmploymentContract::query()->where('employment_profile_id', $profileId)->whereDate('starts_on', $value)->exists()) {
            $fail(__('validation.unique', ['attribute' => $attribute]));
        }
    }
}
