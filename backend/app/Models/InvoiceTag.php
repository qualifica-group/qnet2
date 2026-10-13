<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\InvoiceTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice tag lookup entity (spec 0247, D-5), e.g. estimate/final. The tag of
 * an invoice is optional, so there is no default row. `color` is a token of
 * `App\Support\BadgeTokens::colors()`, validated server-side by the FormRequest.
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'is_active'])]
class InvoiceTag extends BaseModel
{
    /** @use HasFactory<InvoiceTagFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
        ];
    }

    /**
     * The invoices carrying this tag: the "in use" set the delete guard reads.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
