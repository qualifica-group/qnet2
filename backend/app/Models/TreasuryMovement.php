<?php

namespace App\Models;

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TreasuryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual treasury movement (spec 0360): income, expense, transfer between two
 * accounts of the same company, or balance adjustment. Exactly one of the three
 * sub-account columns is set. The amount is always positive; the sign comes from
 * the type (and `direction` for an adjustment).
 */
#[Fillable([
    'type', 'direction', 'movement_date', 'financial_account_id', 'counterpart_account_id', 'amount',
    'description', 'cost_type_id', 'revenue_type_id', 'treasury_type_id', 'registry_id', 'work_order_id',
    'created_by',
])]
class TreasuryMovement extends BaseModel
{
    /** @use HasFactory<TreasuryMovementFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TreasuryMovementType::class,
            'direction' => TreasuryMovementDirection::class,
            'movement_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function counterpartAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'counterpart_account_id');
    }

    /**
     * @return BelongsTo<CostType, $this>
     */
    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    /**
     * @return BelongsTo<RevenueType, $this>
     */
    public function revenueType(): BelongsTo
    {
        return $this->belongsTo(RevenueType::class);
    }

    /**
     * @return BelongsTo<TreasuryType, $this>
     */
    public function treasuryType(): BelongsTo
    {
        return $this->belongsTo(TreasuryType::class);
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
