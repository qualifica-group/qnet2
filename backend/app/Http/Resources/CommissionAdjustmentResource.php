<?php

namespace App\Http\Resources;

use App\Models\CommissionAccrual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a manual adjustment, an ADJUSTMENT movement of the commission
 * ledger (spec 0213, D-7). Needs `company` and `recipient` eager-loaded.
 *
 * @mixin CommissionAccrual
 */
class CommissionAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'referent' => ['id' => $this->recipient_id, 'name' => $this->recipient?->name],
            'company' => ['id' => $this->company_id, 'name' => $this->company?->denomination],
            'amount' => $this->amount,
            'accrued_on' => $this->accrued_on->format('Y-m-d'),
            'notes' => $this->notes,
            'commission_settlement_id' => $this->commission_settlement_id,
        ];
    }
}
