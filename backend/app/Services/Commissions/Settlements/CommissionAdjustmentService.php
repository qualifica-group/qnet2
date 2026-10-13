<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionAccrualType;
use App\Enums\CommissionSettlementStatus;
use App\Enums\HttpStatusEnum;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual adjustments of the commission ledger (spec 0213, D-7): signed
 * ADJUSTMENT movements owed to a referent for a company. Standalone ones wait
 * for the next run; the ones created in a draft are attached at once and the
 * draft is recalculated. An adjustment inside an issued settlement is frozen.
 */
class CommissionAdjustmentService
{
    public function __construct(private readonly CommissionSettlementService $settlements) {}

    /**
     * @param  array{referent_id: int, company_id: int, amount: string, accrued_on: string, notes: string, commission_settlement_id?: int|null}  $data
     */
    public function create(array $data, User $actor): CommissionAccrual
    {
        $adjustment = DB::transaction(function () use ($data, $actor): CommissionAccrual {
            // Step 1: serialize with the runs and the recalculations of the agent, then check the draft
            Referent::query()->whereKey($data['referent_id'])->lockForUpdate()->first();
            $settlementId = $data['commission_settlement_id'] ?? null;
            $settlement = $settlementId === null ? null : $this->lockedDraftFor($settlementId, $data['referent_id'], $data['company_id']);

            // Step 2: post the movement
            $adjustment = CommissionAccrual::query()->create([
                'type' => CommissionAccrualType::Adjustment,
                'recipient_role' => null,
                'recipient_type' => 'referent',
                'recipient_id' => $data['referent_id'],
                'company_id' => $data['company_id'],
                'commission_settlement_id' => $settlement?->id,
                'base_amount' => '0.00',
                'amount' => $data['amount'],
                'accrued_on' => $data['accrued_on'],
                'notes' => $data['notes'],
                'created_by' => $actor->id,
            ]);

            // Step 3: the draft takes the new movement into its totals
            $this->recalculate($settlement);
            $this->audit('created', $adjustment, $actor, $settlement, null);

            return $adjustment;
        });

        return $this->detail($adjustment);
    }

    /**
     * @param  array{amount: string, accrued_on: string, notes: string}  $data
     */
    public function update(CommissionAccrual $adjustment, array $data, User $actor): CommissionAccrual
    {
        $this->mutate($adjustment, $actor, 'updated', fn (CommissionAccrual $locked) => $locked->update([
            'amount' => $data['amount'],
            'accrued_on' => $data['accrued_on'],
            'notes' => $data['notes'],
        ]));

        return $this->detail($adjustment);
    }

    public function delete(CommissionAccrual $adjustment, User $actor): void
    {
        $this->mutate($adjustment, $actor, 'deleted', fn (CommissionAccrual $locked) => $locked->delete());
    }

    public function detail(CommissionAccrual $adjustment): CommissionAccrual
    {
        return CommissionAccrual::query()->with(['company:id,denomination', 'recipient'])->findOrFail($adjustment->id);
    }

    /**
     * Applies `$change` to an adjustment under the agent lock and, when it sits
     * in a draft, recalculates the draft. 404 for a movement that is not an
     * adjustment, 409 when its settlement is not a draft.
     *
     * @param  callable(CommissionAccrual): mixed  $change
     */
    private function mutate(CommissionAccrual $adjustment, User $actor, string $event, callable $change): void
    {
        if ($adjustment->type !== CommissionAccrualType::Adjustment) {
            abort(HttpStatusEnum::NOT_FOUND->value, __('Resource not found.'));
        }

        DB::transaction(function () use ($adjustment, $actor, $event, $change): void {
            // Step 1: lock the agent, then re-read the movement and its settlement
            Referent::query()->whereKey($adjustment->recipient_id)->lockForUpdate()->first();
            $locked = CommissionAccrual::query()->lockForUpdate()->findOrFail($adjustment->id);
            $settlement = $locked->commission_settlement_id === null
                ? null
                : CommissionSettlement::query()->lockForUpdate()->findOrFail($locked->commission_settlement_id);

            if ($settlement !== null && $settlement->status !== CommissionSettlementStatus::Draft) {
                abort(HttpStatusEnum::CONFLICT->value, __('The adjustment belongs to a settlement that is not a draft.'));
            }

            // Step 2: apply, then refresh the draft totals
            $old = $this->snapshot($locked);
            $change($locked);
            $this->recalculate($settlement);

            // Step 3: audit
            $this->audit($event, $locked, $actor, $settlement, $old);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CommissionAccrual $adjustment): array
    {
        return [
            'amount' => $adjustment->amount,
            'accrued_on' => $adjustment->accrued_on?->toDateString(),
            'notes' => $adjustment->notes,
            'referent_id' => $adjustment->recipient_id,
            'company_id' => $adjustment->company_id,
            'commission_settlement_id' => $adjustment->commission_settlement_id,
        ];
    }

    /**
     * Audit trail of a manual adjustment. The ledger model is not logged as a
     * whole (the synchronizer would flood it), so the three events are written
     * by hand: on the settlement the adjustment belongs to, else on the
     * adjustment itself, else (after a delete) on the referent.
     *
     * @param  array<string, mixed>|null  $old  values before an update or delete
     */
    private function audit(string $event, CommissionAccrual $adjustment, User $actor, ?CommissionSettlement $settlement, ?array $old): void
    {
        $subject = $settlement ?? ($event === 'deleted' ? Referent::query()->find($adjustment->recipient_id) : $adjustment);
        $properties = $event === 'deleted'
            ? ['old' => $old]
            : ['new' => $this->snapshot($adjustment), ...($old === null ? [] : ['old' => $old])];

        activity($adjustment->getTable())
            ->performedOn($subject)
            ->causedBy($actor)
            ->event("commission-adjustment.{$event}")
            ->withProperties($properties)
            ->log("Commission adjustment {$event}");
    }

    private function lockedDraftFor(int $settlementId, int $referentId, int $companyId): CommissionSettlement
    {
        $settlement = CommissionSettlement::query()->lockForUpdate()->find($settlementId);

        if ($settlement === null
            || (int) $settlement->referent_id !== $referentId
            || (int) $settlement->company_id !== $companyId
            || $settlement->status !== CommissionSettlementStatus::Draft) {
            throw ValidationException::withMessages([
                'commission_settlement_id' => __('The settlement must be a draft of the same agent and company.'),
            ]);
        }

        return $settlement;
    }

    private function recalculate(?CommissionSettlement $settlement): void
    {
        if ($settlement !== null) {
            $this->settlements->recalculate($settlement);
        }
    }
}
