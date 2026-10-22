<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AbsenceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Absence type catalogue entry (spec 0278 D-5). A type used by a request is
 * deactivated, never deleted.
 */
#[Fillable([
    'name', 'deducts_balance', 'requires_approval', 'is_active', 'sort_order',
    'allows_full_day', 'allows_half_day', 'allows_hourly', 'min_notice_days', 'color', 'default_annual_days',
])]
class AbsenceType extends BaseModel
{
    /** @use HasFactory<AbsenceTypeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * Badge palette of a type (spec 0304 D-11), the `#[Color]` set.
     *
     * @var array<int, string>
     */
    public const array COLORS = ['slate', 'gray', 'amber', 'blue', 'emerald', 'green', 'red', 'violet'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deducts_balance' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'allows_full_day' => 'boolean',
            'allows_half_day' => 'boolean',
            'allows_hourly' => 'boolean',
            'min_notice_days' => 'integer',
            'default_annual_days' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<AbsenceRequest, $this>
     */
    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }
}
