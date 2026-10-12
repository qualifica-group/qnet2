<?php

namespace App\Models;

use App\Enums\PurchaseInvoiceType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payable document, proforma or invoice from a supplier (spec 0211, D-1).
 * Phase 1 has no write endpoint: `created_by` is set by the seeder and, later,
 * by the module that owns the document form.
 */
#[Fillable([
    'document_type', 'company_id', 'supplier_id', 'document_number', 'document_date',
    'payment_method_id', 'financial_account_id', 'cost_type_id', 'company_site_id',
    'operational_site_id', 'work_order_id', 'net_amount', 'vat_amount', 'total_amount',
    'notes', 'created_by',
])]
class PurchaseInvoice extends BaseModel
{
    /** @use HasFactory<PurchaseInvoiceFactory> */
    use HasFactory, LogsModelActivity;

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
     * @return HasMany<PurchaseInvoiceInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceInstallment::class)->orderBy('sequence');
    }
}
