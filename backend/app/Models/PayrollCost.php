<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PayrollCostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cost of one person for one company in one month (`period` "YYYY-MM"), as
 * the labour consultant's cost file states it (spec 0430). Written only by
 * the import, which replaces a whole period at once (D-2).
 */
#[Fillable([
    'company_id', 'user_id', 'period', 'tax_code', 'salary_cost', 'contribution_cost', 'insurance_cost',
    'total_cost', 'ordinary_hours', 'hired_on', 'terminated_on', 'imported_by',
])]
class PayrollCost extends BaseModel
{
    /** @use HasFactory<PayrollCostFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'salary_cost' => 'decimal:2',
            'contribution_cost' => 'decimal:2',
            'insurance_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'ordinary_hours' => 'decimal:2',
            'hired_on' => 'date:Y-m-d',
            'terminated_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
