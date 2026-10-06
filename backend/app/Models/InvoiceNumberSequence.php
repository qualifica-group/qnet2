<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progressive counter per (issuing company, year) (spec 0194, D-6): incremented
 * under lockForUpdate by the number allocator, so a deleted number is never reused.
 */
#[Fillable(['company_id', 'year', 'last_number'])]
class InvoiceNumberSequence extends BaseModel
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
