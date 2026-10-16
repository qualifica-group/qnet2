<?php

namespace App\Models;

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
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
            'salary_amount' => 'decimal:2',
            'salary_period' => SalaryPeriodEnum::class,
        ];
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
     * @return BelongsTo<CollectiveAgreementLevel, $this>
     */
    public function collectiveAgreementLevel(): BelongsTo
    {
        return $this->belongsTo(CollectiveAgreementLevel::class);
    }
}
