<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\PurchaseOrderNumberSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progressive counter per (company, year) (spec 0210, D-11): incremented under
 * lockForUpdate by the number allocator, so a deleted number is never reused.
 */
#[Fillable(['company_id', 'year', 'last_number'])]
class PurchaseOrderNumberSequence extends BaseModel
{
    /** @use HasFactory<PurchaseOrderNumberSequenceFactory> */
    use HasFactory;

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
