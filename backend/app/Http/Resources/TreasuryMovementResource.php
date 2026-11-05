<?php

namespace App\Http\Resources;

use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a TreasuryMovement (spec 0360). Relations are eager loaded by
 * TreasuryMovementService::detail().
 *
 * @mixin TreasuryMovement
 */
class TreasuryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'direction' => $this->direction,
            'movement_date' => $this->movement_date->toDateString(),
            'amount' => (string) $this->amount,
            'account' => $this->accountSummary($this->account),
            'counterpart_account' => $this->counterpartAccount === null ? null : $this->accountSummary($this->counterpartAccount),
            'description' => $this->description,
            'sub_account' => $this->subAccount(),
            'registry' => $this->registry === null ? null : ['id' => $this->registry->id, 'name' => $this->registry->name],
            'work_order' => $this->workOrder === null ? null : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code],
            'reconciled' => $this->isReconciled(),
            'attachments_count' => (int) ($this->attachments_count ?? 0),
            'created_by' => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string, type: mixed, company: array{id: int, name: string}|null}
     */
    private function accountSummary(FinancialAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
            'company' => $account->company === null ? null : ['id' => $account->company->id, 'name' => $account->company->denomination],
        ];
    }

    /**
     * @return array{kind: string, id: int, code: string, name: string}
     */
    private function subAccount(): array
    {
        [$kind, $related] = match (true) {
            $this->costType !== null => ['cost', $this->costType],
            $this->revenueType !== null => ['revenue', $this->revenueType],
            default => ['financial', $this->treasuryType],
        };

        return ['kind' => $kind, 'id' => $related->id, 'code' => $related->code, 'name' => $related->name];
    }
}
