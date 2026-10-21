<?php

namespace App\Models;

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Enums\WorkingTimeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EmploymentContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One contractual condition of an employment profile (spec 0272 D-1):
 * append-only, valid from `starts_on` until the next condition starts.
 *
 * `salary_amount` and `salary_period` are $hidden on purpose: the activity log
 * excludes hidden attributes, so compensation never reaches the log, and no
 * default serialization leaks it. EmploymentContractResource exposes them
 * explicitly, only with `users.manageCompensation` (D-5).
 */
#[Fillable([
    'employment_profile_id',
    'starts_on',
    'relationship_type',
    'contract_type_id',
    'working_time',
    'contract_expires_on',
    'company_id',
    'collective_agreement_level_id',
    'salary_amount',
    'salary_period',
    'change_reason',
])]
class EmploymentContract extends BaseModel
{
    /** @use HasFactory<EmploymentContractFactory> */
    use HasFactory, LogsModelActivity;

    /** @var list<string> */
    protected $hidden = ['salary_amount', 'salary_period'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'relationship_type' => RelationshipTypeEnum::class,
            'working_time' => WorkingTimeEnum::class,
            'contract_expires_on' => 'date:Y-m-d',
            'salary_amount' => 'decimal:2',
            'salary_period' => SalaryPeriodEnum::class,
        ];
    }

    /**
     * The hourly rate this condition's salary amounts to (spec 0277 D-1),
     * rounded to the cent; null when the condition carries no salary.
     */
    public function hourlyCost(): ?string
    {
        if ($this->salary_amount === null || $this->salary_period === null) {
            return null;
        }

        return number_format(round((float) $this->salary_amount / $this->salary_period->hours(), 2), 2, '.', '');
    }

    /**
     * @return BelongsTo<EmploymentProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    /**
     * @return BelongsTo<CollectiveAgreementLevel, $this>
     */
    public function collectiveAgreementLevel(): BelongsTo
    {
        return $this->belongsTo(CollectiveAgreementLevel::class);
    }
}
