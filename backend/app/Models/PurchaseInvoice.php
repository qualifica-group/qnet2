<?php

namespace App\Models;

use App\Enums\PurchaseInvoiceType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payable document, proforma or invoice from a supplier (spec 0211, D-1; lines
 * and write path in spec 0250). `document_year` is derived on save: the year of
 * the document date for an invoice, null for a proforma (D-7).
 */
#[Fillable([
    'document_type', 'company_id', 'supplier_id', 'document_number', 'document_date', 'document_year',
    'payment_method_id', 'financial_account_id', 'cost_type_id', 'company_site_id',
    'operational_site_id', 'work_order_id', 'net_amount', 'vat_amount', 'total_amount',
    'notes', 'created_by',
])]
class PurchaseInvoice extends BaseModel
{
    /** @use HasFactory<PurchaseInvoiceFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            $document->document_year = $document->isRegistered() ? $document->document_date->year : null;
        });
    }

    /** Registered by the supplier number: frozen but for its details (spec 0250, D-2). */
    public function isRegistered(): bool
    {
        return $this->document_type === PurchaseInvoiceType::Invoice;
    }

    /** Whether any installment has a payment; reads the loaded schedule when there is one. */
    public function hasPayments(): bool
    {
        if ($this->relationLoaded('installments')) {
            return $this->installments->contains(fn (PurchaseInvoiceInstallment $row): bool => $row->isPaid());
        }

        return $this->installments()->where('paid_amount', '>', 0)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'document_type' => PurchaseInvoiceType::class,
            'document_date' => 'date',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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
     * @return BelongsTo<Registry, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * @return BelongsTo<CostType, $this>
     */
    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsToMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'purchase_invoice_purchase_order')->withTimestamps();
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<PurchaseInvoiceInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceInstallment::class)->orderBy('sequence');
    }
}
