<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\CommissionSettlementRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One launch of the agent commission settlement (spec 0213, D-6): the period,
 * the filters used and the (agent, company) pairs left out with their reason.
 * Read-only history: a run is never edited after creation.
 */
#[Fillable(['period_from', 'period_to', 'filters', 'min_amount', 'settlements_count', 'skipped', 'created_by'])]
class CommissionSettlementRun extends BaseModel
{
    /** @use HasFactory<CommissionSettlementRunFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'filters' => 'array',
            'min_amount' => 'decimal:2',
            'settlements_count' => 'integer',
            'skipped' => 'array',
        ];
    }

    /**
     * @return HasMany<CommissionSettlement, $this>
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
