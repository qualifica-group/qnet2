<?php

namespace App\Models;

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestPriority;
use App\Enums\PurchaseRequestStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;

/**
 * Purchase request, "RDA" (spec 0208). `created_by`, the totals and the closure
 * columns are NOT fillable: the service writes them (D-12, D-7), never the client.
 */
#[Fillable([
    'subject', 'requested_at', 'priority', 'requester_id', 'function_manager_id', 'customer_id',
    'supplier_id', 'work_order_id', 'company_id', 'company_site_id', 'operational_site_id',
    'business_function_id', 'notes', 'delivery_terms', 'procurement_plan',
    'technical_requirements', 'special_conditions',
])]
class PurchaseRequest extends BaseModel
{
    /** @use HasFactory<PurchaseRequestFactory> */
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
            'requested_at' => 'date:Y-m-d',
            'priority' => PurchaseRequestPriority::class,
            'status' => PurchaseRequestStatus::class,
            'taxable_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The closure columns are not fillable, but they belong in the audit trail.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->defaultActivitylogOptions()->logOnly([
            ...$this->getFillable(), 'status', 'closed_by', 'close_reason',
        ]);
    }

    public function isClosed(): bool
    {
        return $this->status === PurchaseRequestStatus::Closed;
    }

    /**
     * Lines per status, every status present (zero filled). Reads the loaded
     * `lines` relation: callers eager load it.
     *
     * @return array<string, int>
     */
    public function lineStatusCounts(): array
    {
        $counts = array_fill_keys(PurchaseRequestLineStatus::values(), 0);

        foreach ($this->lines as $line) {
            $counts[$line->status->value]++;
        }

        return $counts;
    }

    /**
     * Display name of the operational site: its alias, else "{line1} - {city}"
     * from the primary address. Needs `operationalSite.addresses.city` loaded.
     */
    public function operationalSiteLabel(): string
    {
        $site = $this->operationalSite;

        if (filled($site->alias)) {
            return (string) $site->alias;
        }

        $address = $site->primary_address;
        $city = $address?->city?->localizedName();

        return $city === null ? (string) $address?->line1 : "{$address?->line1} - {$city}";
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function functionManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'function_manager_id');
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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
     * @return BelongsTo<BusinessFunction, $this>
     */
    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<PurchaseRequestLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class)->orderBy('position')->orderBy('id');
    }
}
