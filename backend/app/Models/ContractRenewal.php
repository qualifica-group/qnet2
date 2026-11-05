<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ContractRenewalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One renewal period of a contract (spec 0358, D-2): "Rinnovo N". Written only
 * by ContractRenewalService. `renewed_by` is deliberately NOT #[Fillable]: the
 * service assigns the actor, a client can never choose it.
 */
#[Fillable(['contract_id', 'number', 'starts_on', 'previous_renewal_date', 'previous_expiry_date', 'renewal_date', 'expiry_date'])]
class ContractRenewal extends BaseModel
{
    /** @use HasFactory<ContractRenewalFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'starts_on' => 'date',
            'previous_renewal_date' => 'date',
            'previous_expiry_date' => 'date',
            'renewal_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
