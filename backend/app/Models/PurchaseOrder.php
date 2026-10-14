<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;

/**
 * Purchase order, "ODA" (spec 0210). `number`, `year`, `status`, the totals,
 * `copy_group_id` and `created_by` are NOT fillable: the services write them
 * (D-11, D-12, D-16), never the client.
 */
#[Fillable([
    'supplier_id', 'company_id', 'company_site_id', 'operational_site_id', 'order_date',
    'description', 'general_notes', 'payment_method_id', 'is_internal_user', 'customer_id',
    'work_order_id', 'quote_id', 'opportunity_id', 'sector_notes',
])]
class PurchaseOrder extends BaseModel
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasAttachments, HasFactory;

    use LogsModelActivity {
        getActivitylogOptions as private defaultActivitylogOptions;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'number' => 'int',
            'year' => 'int',
            'order_date' => 'date:Y-m-d',
            'is_internal_user' => 'bool',
            'status' => PurchaseOrderStatus::class,
            'taxable_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /**
     * The status is not fillable, but it belongs in the audit trail.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->defaultActivitylogOptions()->logOnly([...$this->getFillable(), 'status']);
    }

    /**
     * Public identifier "{number}/{year}" (D-11).
     */
    public function code(): string
    {
        return "{$this->number}/{$this->year}";
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<CompanySite, $this>
     */
    public function companySite(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class);
    }

    /**
     * @return BelongsTo<OperationalSite, $this>
     */
    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'customer_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsToMany<Sector, $this>
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'purchase_order_sector');
    }

    /**
     * Payable documents billing this order (spec 0211, D-2).
     *
     * @return BelongsToMany<PurchaseInvoice, $this>
     */
    public function purchaseInvoices(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseInvoice::class, 'purchase_invoice_purchase_order')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Merges where this order is the destination, most recent first.
     *
     * @return HasMany<PurchaseOrderMergeLog, $this>
     */
    public function mergeLogs(): HasMany
    {
        return $this->hasMany(PurchaseOrderMergeLog::class, 'destination_purchase_order_id')->latest('id');
    }

    /**
     * @return HasMany<PurchaseOrderReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class)->orderByDesc('receipt_date')->orderByDesc('id');
    }
}
