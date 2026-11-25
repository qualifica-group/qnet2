<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payroll detail of a `payslip` payable document (spec 0431, D-2): company,
 * person, month (`period` "YYYY-MM") and payslip type of the consultant's net
 * file. Written only by the payslip import.
 */
#[Fillable(['purchase_invoice_id', 'company_id', 'user_id', 'period', 'payslip_type', 'employee_code'])]
class Payslip extends BaseModel
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
