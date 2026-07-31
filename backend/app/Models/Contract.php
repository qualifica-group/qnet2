<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contract entity (spec 0072): the additional lifecycle data for a Quote that
 * reached a `closed_won` status (D-6). NEVER created or deleted by hand — it
 * is born only from `App\Services\Contracts\ContractLifecycleManager` (BR-1)
 * and lives exactly as long as its Quote (`quote_id` cascadeOnDelete). Only
 * the four fields a PATCH may actually touch are #[Fillable] (data_model
 * `<fillable>`): `contract_status_id`/`renewal_date`/`expiry_date`/
 * `payment_notes`/`comments`. Every other column —
 * `quote_id`/`accepted_at`/`validated_at`/`validated_by`/`terminated_at`/
 * `terminated_by`/`termination_reason`/`suspended_at`/
 * `status_before_suspension_id` — is written exclusively by the domain
 * services (forceFill/direct assignment), never mass-assignable.
 */
#[Fillable(['contract_status_id', 'renewal_date', 'expiry_date', 'payment_notes', 'comments'])]
class Contract extends BaseModel
{
    /** @use HasFactory<ContractFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'date',
            'validated_at' => 'date',
            'renewal_date' => 'date',
            'expiry_date' => 'date',
            'terminated_at' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * The Quote this contract's lifecycle data belongs to — the single
     * source of truth for client, opportunity, products, amounts and
     * documents (D-1). Never re-assignable after creation.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * The contract's working-state classification (spec 0072, D-2):
     * mandatory, restrictOnDelete.
     */
    public function contractStatus(): BelongsTo
    {
        return $this->belongsTo(ContractStatus::class);
    }

    /**
     * The status this contract was in right before the automatic suspension
     * (D-3), restored verbatim by the "Riattiva contratto" action (BR-2).
     */
    public function statusBeforeSuspension(): BelongsTo
    {
        return $this->belongsTo(ContractStatus::class, 'status_before_suspension_id');
    }

    /**
     * The User who validated this contract (BR-3), nullOnDelete.
     */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * The User who terminated ("disdetto") this contract (BR-4), nullOnDelete.
     */
    public function terminatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    /**
     * Whether the contract is currently suspended (D-3): its Quote left
     * `closed_won` and no explicit "Riattiva contratto" has restored it yet.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
