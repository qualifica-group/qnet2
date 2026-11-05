<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved bank reconciliation (spec 0363): the bank balance of an account at a
 * date, the reconciled balance computed then and their difference. At most one
 * per account and date; the optional adjustment movement it created stays a
 * normal movement when the reconciliation is deleted (D-9).
 */
#[Fillable([
    'financial_account_id', 'statement_date', 'bank_balance', 'reconciled_balance', 'difference',
    'adjustment_movement_id', 'note', 'created_by',
])]
class BankReconciliation extends BaseModel
{
    use LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'bank_balance' => 'decimal:2',
            'reconciled_balance' => 'decimal:2',
            'difference' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
