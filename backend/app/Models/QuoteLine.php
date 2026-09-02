<?php

namespace App\Models;

use App\Enums\QuoteLineType;
use App\Models\Abstracts\BaseModel;
use Database\Factories\QuoteLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One revenue or cost row against a Quote (spec 0065, D-11): revenue and cost
 * lines share this ONE table, discriminated by `line_type`. The line stores
 * ONLY `product_id` (D-7): code, name, category and business function are
 * read live from the Product at response time, never duplicated.
 * `net_amount`/`vat_amount`/`total_amount` ARE persisted and frozen (D-10/
 * D-12): computed server-side from `quantity` * `unit_price` (+ the VAT rate
 * snapshot at that moment), rounded half-up to 2 decimals, so a later change
 * to `vat_rates.rate` never alters an already-saved Quote. `unit_of_measure_id`
 * (spec 0088, D-5) EMENDS D-7 for this one field only: it is frozen from the
 * Product at write time (QuoteLineWriter::sync()) because it qualifies the
 * already-frozen `quantity` — reading it live would let 10 Kg silently become
 * 10 Grammi after a later product edit. No activity log on this row (pure
 * child collection of the Quote, which already logs its own changes — mirrors
 * OpportunityProductLine): it is written exclusively by the quote service's
 * full-replace (D-8), never directly by a client.
 */
#[Fillable([
    'quote_id',
    'line_type',
    'product_id',
    'quantity',
    'unit_price',
    'vat_rate_id',
    'unit_of_measure_id',
    'net_amount',
    'vat_amount',
    'total_amount',
    'sort_order',
])]
class QuoteLine extends BaseModel
{
    /** @use HasFactory<QuoteLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => QuoteLineType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sort_order' => 'int',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * The unit of measure frozen onto this row at write time (spec 0088,
     * D-5) — nullable: a historic line predating the module reads as null,
     * and QuoteLineResource falls back to the product's CURRENT unit.
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(QuoteLineCommission::class);
    }

    /**
     * The inverse of WorkOrder::quoteLines() (spec 0095, D-5): the work
     * order(s) this line has been programmed into, via the SAME
     * `quote_line_work_order` pivot. D-4's `UNIQUE(quote_line_id)` guarantees
     * this collection never holds more than one row — the relation stays
     * BelongsToMany (the pivot's own shape), never a BelongsTo, so a future
     * per-row pivot column stays reachable the same way from either side.
     *
     * @return BelongsToMany<WorkOrder, $this>
     */
    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrder::class, 'quote_line_work_order');
    }
}
