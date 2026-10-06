<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderCostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ACTUAL cost of a commessa (spec 0190, D-2): the fields of an offer COST
 * line (product, quantity, frozen unit, unit price, VAT rate, server-computed
 * amounts, additional description) plus its own date, supplier and document
 * reference. The three amounts and `unit_of_measure_id` are written only by
 * `WorkOrderCostWriter`, never by the client.
 */
#[Fillable([
    'work_order_id',
    'product_id',
    'quote_line_id',
    'quantity',
    'unit_of_measure_id',
    'unit_price',
    'vat_rate_id',
    'net_amount',
    'vat_amount',
    'total_amount',
    'incurred_on',
    'supplier_id',
    'document_reference',
    'additional_description',
    'sort_order',
])]
class WorkOrderCost extends BaseModel
{
    /** @use HasFactory<WorkOrderCostFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'incurred_on' => 'date:Y-m-d',
            'sort_order' => 'int',
        ];
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The commessa's REVENUE line this cost is imputed to; null = unattributed.
     *
     * @return BelongsTo<QuoteLine, $this>
     */
    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /** @return BelongsTo<Registry, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }
}
