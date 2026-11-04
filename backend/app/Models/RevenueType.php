<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\RevenueTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revenue sub-account of the managerial chart of accounts (spec 0348, D-3):
 * the revenue counterpart of CostType, child of an account under a revenue group.
 */
#[Fillable(['chart_account_id', 'code', 'name', 'description', 'is_active', 'is_institution_commission_default'])]
class RevenueType extends BaseModel
{
    /** @use HasFactory<RevenueTypeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'is_institution_commission_default' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<ChartAccount, $this>
     */
    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }
}
