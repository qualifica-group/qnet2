<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The closing of a receivable and a payable installment of the same registry
 * and company against each other (spec 0364): not money, so it never appears
 * on an account statement. Cancelling it restores both installments.
 */
#[Fillable([
    'company_id', 'registry_id', 'invoice_installment_id', 'purchase_invoice_installment_id',
    'amount', 'compensated_at', 'note', 'created_by',
])]
class InstallmentCompensation extends BaseModel
{
    use LogsModelActivity;

    /** Set explicitly: the default table name guess drops the plural for this word. */
    protected $table = 'installment_compensations';

    /** Relations the resource reads. */
    public const array RELATIONS = [
        'company:id,denomination',
        'invoiceInstallment.invoice:id,number,year',
        'purchaseInvoiceInstallment.purchaseInvoice:id,document_number',
        'creator:id,name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'compensated_at' => 'date',
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
    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    /**
     * @return BelongsTo<InvoiceInstallment, $this>
     */
    public function invoiceInstallment(): BelongsTo
    {
        return $this->belongsTo(InvoiceInstallment::class);
    }

    /**
     * @return BelongsTo<PurchaseInvoiceInstallment, $this>
     */
    public function purchaseInvoiceInstallment(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceInstallment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
