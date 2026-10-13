<?php

namespace App\Http\Resources;

use App\Enums\CommissionAccrualType;
use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail projection of a CommissionSettlement (spec 0213): totals, snapshot,
 * movements and the actions the actor can run right now (permission AND
 * state). Amounts hidden by field permission are dropped, the movements' own
 * amounts following the `commission-accruals` visibility.
 *
 * @mixin CommissionSettlement
 */
class CommissionSettlementResource extends JsonResource
{
    /** Hiding the taxable amount hides the two sums it is made of. */
    private const array DERIVED_FROM_TAXABLE = ['accruals_amount', 'adjustments_amount'];

    /**
     * @param  array<string, array{visible?: bool}>  $fieldPermissions  of commission-settlements
     * @param  array<string, array{visible?: bool}>  $movementFieldPermissions  of commission-accruals
     */
    public function __construct($resource, private readonly array $fieldPermissions = [], private readonly array $movementFieldPermissions = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $actor */
        $actor = $request->user();

        $payload = [
            'id' => $this->id,
            'code' => $this->code(),
            'status' => $this->status->value,
            'period_from' => $this->period_from?->format('Y-m-d'),
            'period_to' => $this->period_to?->format('Y-m-d'),
            'run_id' => $this->commission_settlement_run_id,
            'company' => ['id' => $this->company->id, 'name' => $this->company->denomination],
            'referent' => ['id' => $this->referent->id, 'name' => $this->referent->name],
            'agent_fiscal_profile_id' => $this->agent_fiscal_profile_id,
            'accruals_amount' => $this->accruals_amount,
            'adjustments_amount' => $this->adjustments_amount,
            'taxable_amount' => $this->taxable_amount,
            'vat_rate' => $this->vat_rate,
            'vat_amount' => $this->vat_amount,
            'withholding_enabled' => $this->withholding_enabled,
            'withholding_rate' => $this->withholding_rate,
            'withholding_base_percent' => $this->withholding_base_percent,
            'withholding_amount' => $this->withholding_amount,
            'enasarco_enabled' => $this->enasarco_enabled,
            'enasarco_mandate_type' => $this->enasarco_mandate_type?->value,
            'enasarco_base_amount' => $this->enasarco_base_amount,
            'enasarco_agent_amount' => $this->enasarco_agent_amount,
            'enasarco_principal_amount' => $this->enasarco_principal_amount,
            'net_payable' => $this->net_payable,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at,
            'issued_by' => $this->issuer === null ? null : ['id' => $this->issuer->id, 'name' => $this->issuer->name],
            'cancelled_at' => $this->cancelled_at,
            'cancelled_by' => $this->canceller === null ? null : ['id' => $this->canceller->id, 'name' => $this->canceller->name],
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'movements' => $this->movements->map(fn (CommissionAccrual $movement): array => $this->movement($movement, $actor))->values()->all(),
            'abilities' => $this->abilities($actor),
        ];

        return $this->withoutHidden($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function movement(CommissionAccrual $movement, User $actor): array
    {
        $row = [
            'id' => $movement->id,
            'type' => $movement->type->value,
            'recipient_role' => $movement->recipient_role?->value,
            'accrued_on' => $movement->accrued_on->format('Y-m-d'),
            'amount' => $movement->amount,
            'base_amount' => $movement->base_amount,
            'notes' => $movement->notes,
            'invoice' => $movement->invoice === null ? null : [
                'id' => $movement->invoice->id,
                'code' => "{$movement->invoice->number}/{$movement->invoice->year}",
            ],
            'quote' => $movement->quoteLine?->quote === null ? null : [
                'id' => $movement->quoteLine->quote->id,
                'code' => $movement->quoteLine->quote->code,
            ],
            'product' => $movement->quoteLine?->product?->name,
            'can_edit' => $movement->type === CommissionAccrualType::Adjustment
                && $this->status === CommissionSettlementStatus::Draft
                && $actor->can('commission-settlements.adjust'),
        ];

        foreach ($this->movementFieldPermissions as $field => $permission) {
            if (($permission['visible'] ?? true) !== true) {
                unset($row[$field]);
            }
        }

        return $row;
    }

    /**
     * Each action = the ability AND the state it needs (D-5): a draft is
     * edited, recalculated, adjusted, issued and deleted; an issued one is
     * only cancelled.
     *
     * @return array<string, bool>
     */
    private function abilities(User $actor): array
    {
        $draft = $this->status === CommissionSettlementStatus::Draft;

        return [
            'recalculate' => $draft && $actor->can('commission-settlements.update'),
            'issue' => $draft && $actor->can('commission-settlements.issue'),
            'cancel' => $this->status === CommissionSettlementStatus::Issued && $actor->can('commission-settlements.cancel'),
            'delete' => $draft && $actor->can('commission-settlements.delete'),
            'adjust' => $draft && $actor->can('commission-settlements.adjust'),
            'update' => $draft && $actor->can('commission-settlements.update'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutHidden(array $payload): array
    {
        $hidden = array_keys(array_filter($this->fieldPermissions, fn (array $permission): bool => ($permission['visible'] ?? true) !== true));

        // Derived columns reveal a hidden amount: the sums make the taxable amount, the ENASARCO base bounds the contribution
        if (in_array('taxable_amount', $hidden, true)) {
            $hidden = [...$hidden, ...self::DERIVED_FROM_TAXABLE];
        }

        if (array_intersect(['taxable_amount', 'enasarco_agent_amount', 'enasarco_principal_amount'], $hidden) !== []) {
            $hidden[] = 'enasarco_base_amount';
        }

        return array_diff_key($payload, array_flip($hidden));
    }
}
