<?php

namespace App\Http\Requests\CompanyClosures;

use App\Enums\CompanyClosureKind;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\AbsenceType;
use App\Models\OperationalSite;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/company-closures (spec 0304 D-13). Authorization stays
 * in the controller (CompanyClosurePolicy). PUT sends the whole payload again.
 */
class StoreCompanyClosureRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 100;

    public const int MAX_DAYS = 31;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isLeave = $this->input('kind') === CompanyClosureKind::CollectiveLeave->value;
        $allSites = $this->boolean('applies_to_all_sites');

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'kind' => ['required', Rule::in(CompanyClosureKind::values())],
            'absence_type_id' => $isLeave
                ? ['required', 'integer', Rule::exists(AbsenceType::class, 'id')->where('is_active', true)]
                : ['prohibited'],
            'applies_to_all_sites' => ['required', 'boolean'],
            'operational_site_ids' => $allSites ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'operational_site_ids.*' => ['integer', 'distinct', Rule::exists(OperationalSite::class, 'id')],
            'repeats_yearly' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            if ($validator->errors()->isEmpty()) {
                $this->enforcePeriodRules($validator);
            }
        });
    }

    /** At most 31 days; a yearly closure is a holiday of a single day (the patron saint). */
    private function enforcePeriodRules(Validator $validator): void
    {
        $startsOn = CarbonImmutable::parse($this->input('starts_on'));
        $endsOn = CarbonImmutable::parse($this->input('ends_on'));

        if ($startsOn->diffInDays($endsOn) + 1 > self::MAX_DAYS) {
            $validator->errors()->add('ends_on', __('company_closures.too_long', ['max' => self::MAX_DAYS]));
        }

        $yearlyAllowed = $this->input('kind') === CompanyClosureKind::Holiday->value && $startsOn->equalTo($endsOn);

        if ($this->boolean('repeats_yearly') && ! $yearlyAllowed) {
            $validator->errors()->add('repeats_yearly', __('company_closures.yearly_needs_single_holiday'));
        }
    }

    protected function authorizationResource(): string
    {
        return 'company-closures';
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
