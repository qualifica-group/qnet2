<?php

namespace App\Http\Resources;

use App\Models\BankReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a BankReconciliation (spec 0363). `creator` is eager loaded.
 *
 * @mixin BankReconciliation
 */
class BankReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statement_date' => $this->statement_date->toDateString(),
            'bank_balance' => (string) $this->bank_balance,
            'reconciled_balance' => (string) $this->reconciled_balance,
            'difference' => (string) $this->difference,
            'adjustment_movement_id' => $this->adjustment_movement_id,
            'note' => $this->note,
            'created_by' => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name],
            'created_at' => $this->created_at,
        ];
    }
}
