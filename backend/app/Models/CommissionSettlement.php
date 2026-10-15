<?php

namespace App\Models;

use App\Enums\CommissionSettlementStatus;
use App\Enums\EnasarcoMandateType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CommissionSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent commission settlement statement for one (referent, company) pair
 * (spec 0213). Carries a snapshot of the rates and bases used plus the
 * computed amounts; the number is assigned on issue only.
 */
#[Fillable([
    'commission_settlement_run_id', 'company_id', 'referent_id', 'agent_fiscal_profile_id', 'status',
    'number', 'year', 'period_from', 'period_to', 'accruals_amount', 'adjustments_amount', 'taxable_amount',
    'vat_rate', 'vat_amount', 'withholding_enabled', 'withholding_rate', 'withholding_base_percent',
    'withholding_amount', 'enasarco_enabled', 'enasarco_mandate_type', 'enasarco_base_amount',
    'enasarco_agent_amount', 'enasarco_principal_amount', 'net_payable', 'notes', 'issued_at', 'issued_by',
    'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by',
    'document_number', 'document_date', 'document_taxable_amount', 'document_vat_amount',
    'document_withholding_amount', 'document_enasarco_amount', 'document_total_amount',
    'document_received_at', 'document_received_by', 'approved_at', 'approved_by', 'purchase_invoice_id',
])]
class CommissionSettlement extends BaseModel
{
    /** @use HasFactory<CommissionSettlementFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CommissionSettlementStatus::class,
            'number' => 'integer',
            'year' => 'integer',
            'period_from' => 'date',
            'period_to' => 'date',
            'accruals_amount' => 'decimal:2',
            'adjustments_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'withholding_enabled' => 'boolean',
            'withholding_rate' => 'decimal:2',
            'withholding_base_percent' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'enasarco_enabled' => 'boolean',
            'enasarco_mandate_type' => EnasarcoMandateType::class,
            'enasarco_base_amount' => 'decimal:2',
            'enasarco_agent_amount' => 'decimal:2',
            'enasarco_principal_amount' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'document_date' => 'date',
            'document_taxable_amount' => 'decimal:2',
            'document_vat_amount' => 'decimal:2',
            'document_withholding_amount' => 'decimal:2',
            'document_enasarco_amount' => 'decimal:2',
            'document_total_amount' => 'decimal:2',
            'document_received_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * The "n/year" label shown to users; null until the settlement is issued.
     */
    public function code(): ?string
    {
        return $this->number === null ? null : "{$this->number}/{$this->year}";
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Referent, $this>
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(Referent::class);
    }

    /**
     * @return BelongsTo<AgentFiscalProfile, $this>
     */
    public function agentFiscalProfile(): BelongsTo
    {
        return $this->belongsTo(AgentFiscalProfile::class);
    }

    /**
     * @return BelongsTo<CommissionSettlementRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementRun::class, 'commission_settlement_run_id');
    }

    /**
     * @return HasMany<CommissionAccrual, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function documentReceiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'document_received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
