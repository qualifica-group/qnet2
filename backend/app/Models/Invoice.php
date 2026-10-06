<?php

namespace App\Models;

use App\Enums\InvoiceTag;
use App\Enums\InvoiceType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Active invoicing document (spec 0194): a proforma that becomes an invoice once
 * the external number/date are registered (D-3). Number is the progressive per
 * (company, year) allocated by the service (D-6); amounts are computed
 * server-side (D-9).
 */
#[Fillable([
    'type', 'company_id', 'number', 'year', 'document_date', 'proforma_request_id',
    'work_order_id', 'quote_id', 'customer_registry_id', 'payment_method_id',
    'financial_account_id', 'net_amount', 'vat_amount', 'total_amount', 'external_number',
    'external_date', 'notes', 'internal_note', 'tag', 'deviation', 'created_by',
])]
class Invoice extends BaseModel
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'tag' => InvoiceTag::class,
            'number' => 'int',
            'year' => 'int',
            'document_date' => 'date',
            'external_date' => 'date',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'deviation' => 'decimal:2',
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'customer_registry_id');
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
     * @return BelongsTo<ProformaRequest, $this>
     */
    public function proformaRequest(): BelongsTo
    {
        return $this->belongsTo(ProformaRequest::class);
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
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<InvoiceInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('sequence');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
