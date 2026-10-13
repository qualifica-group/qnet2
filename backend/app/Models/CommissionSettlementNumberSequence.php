<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progressive counter per (company, year) (spec 0213, D-1): incremented under
 * lockForUpdate on issue, so a cancelled settlement number is never reused.
 */
#[Fillable(['company_id', 'year', 'last_number'])]
class CommissionSettlementNumberSequence extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'int',
            'last_number' => 'int',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
