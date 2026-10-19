<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AbsenceAllowanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yearly allowance in minutes for a person and a balance-deducting absence
 * type (spec 0278 D-1). Carry-over may be negative (D-11).
 */
#[Fillable(['user_id', 'absence_type_id', 'year', 'annual_minutes', 'carried_over_minutes'])]
class AbsenceAllowance extends BaseModel
{
    /** @use HasFactory<AbsenceAllowanceFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'annual_minutes' => 'integer',
            'carried_over_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AbsenceType, $this>
     */
    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }
}
