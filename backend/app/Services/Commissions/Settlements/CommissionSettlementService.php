<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementStatus;
use App\Enums\HttpStatusEnum;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\Referent;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State transitions of a settlement (spec 0213, D-5): recalculate, issue,
 * cancel, delete and the draft notes. Every transition runs in a transaction
 * that locks the agent row first (the same order as the runner) and then the
 * settlement, and re-reads the state under the lock: a transition not allowed
 * from the current state answers 409.
 */
class CommissionSettlementService
{
    /** Relations the CommissionSettlementResource reads. */
    private const array DETAIL_RELATIONS = [
        'company:id,denomination',
        'referent:id,name,supplier_registry_id',
        'referent.supplierRegistry:id,name',
        'issuer:id,name',
        'canceller:id,name',
        'documentReceiver:id,name',
        'approver:id,name',
        'purchaseInvoice.installments',
        'movements.invoice:id,number,year',
        'movements.quoteLine:id,quote_id,product_id',
        'movements.quoteLine.quote:id,code',
        'movements.quoteLine.product:id,name',
    ];

    public function __construct(
        private readonly OpenMovementSelector $selector,
        private readonly SettlementComposer $composer,
        private readonly SettlementNumberAllocator $allocator,
    ) {}

    public function recalculate(CommissionSettlement $settlement): CommissionSettlement
    {
        return $this->transition($settlement, function (CommissionSettlement $locked): void {
            $this->assertDraft($locked);
            $this->refresh($locked);
        });
    }

    public function issue(CommissionSettlement $settlement, User $actor): CommissionSettlement
    {
        return $this->transition($settlement, function (CommissionSettlement $locked) use ($actor): void {
            // Step 1: only a draft is issued, after a final recalculation
            $this->assertDraft($locked);
            $this->refresh($locked);

            // Step 2: nothing to pay
            if (bccomp((string) $locked->taxable_amount, '0', 2) <= 0) {
                abort(HttpStatusEnum::CONFLICT->value, __('A settlement with a non-positive taxable amount cannot be issued.'));
            }

            // Step 3: number (year of the period end), then freeze
            $year = $locked->period_to->year;
            $locked->fill([
                'status' => CommissionSettlementStatus::Issued,
                'number' => $this->allocator->next($locked->company_id, $year),
                'year' => $year,
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ])->save();
        });
    }

    public function cancel(CommissionSettlement $settlement, User $actor, string $reason): CommissionSettlement
    {
        return $this->transition($settlement, function (CommissionSettlement $locked) use ($actor, $reason): void {
            if ($locked->status !== CommissionSettlementStatus::Issued) {
                abort(HttpStatusEnum::CONFLICT->value, __('Only an issued settlement can be cancelled.'));
            }

            $this->release($locked);
            $locked->fill([
                'status' => CommissionSettlementStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();
        });
    }

    public function updateNotes(CommissionSettlement $settlement, ?string $notes): CommissionSettlement
    {
        return $this->transition($settlement, function (CommissionSettlement $locked) use ($notes): void {
            $this->assertDraft($locked);
            $locked->update(['notes' => $notes]);
        });
    }

    public function delete(CommissionSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement): void {
            Referent::query()->whereKey($settlement->referent_id)->lockForUpdate()->first();
            $locked = CommissionSettlement::query()->lockForUpdate()->findOrFail($settlement->id);

            $this->assertDraft($locked);
            $this->release($locked);
            $locked->delete();
        });
    }

    /**
     * The settlement with the relations its Resource needs eager-loaded.
     */
    public function detail(CommissionSettlement $settlement): CommissionSettlement
    {
        return CommissionSettlement::query()->with(self::DETAIL_RELATIONS)->findOrFail($settlement->id);
    }

    /**
     * Locks agent and settlement, runs `$step` on the fresh row, returns the detail.
     * Shared with the document and approval services (spec 0267): one lock order everywhere.
     *
     * @param  Closure(CommissionSettlement): void  $step
     */
    public function transition(CommissionSettlement $settlement, Closure $step): CommissionSettlement
    {
        DB::transaction(function () use ($settlement, $step): void {
            Referent::query()->whereKey($settlement->referent_id)->lockForUpdate()->first();
            $step(CommissionSettlement::query()->lockForUpdate()->findOrFail($settlement->id));
        });

        return $this->detail($settlement);
    }

    /**
     * Attaches the movements that became open since the last calculation
     * (honouring the role filter of the run) and recomputes every amount from
     * the movements now attached. Movements already attached stay: that is how
     * a manual adjustment keeps its place whatever its date.
     */
    private function refresh(CommissionSettlement $settlement): void
    {
        // Step 1: attach the newly open movements
        $roles = $this->runRoles($settlement);
        $open = $this->selector->lockedForPair($settlement->referent_id, $settlement->company_id, $settlement->period_to->toDateString(), $roles);
        CommissionAccrual::query()->whereIn('id', $open->modelKeys())->update(['commission_settlement_id' => $settlement->id]);

        // Step 2: totals over everything attached
        $totals = MovementTotals::from(CommissionAccrual::query()->where('commission_settlement_id', $settlement->id)->get());

        // Step 3: recompute with the current profile and parameters
        try {
            $attributes = $this->composer->compose($settlement->referent_id, $settlement->company_id, $settlement->period_to, $totals, $settlement->id);
        } catch (SettlementSkippedException $skipped) {
            throw ValidationException::withMessages(['reason' => [$skipped->reason->value]]);
        }

        $settlement->update($attributes);
    }

    /**
     * @return array<int, string>|null
     */
    private function runRoles(CommissionSettlement $settlement): ?array
    {
        if ($settlement->commission_settlement_run_id === null) {
            return null;
        }

        $run = CommissionSettlementRun::query()->find($settlement->commission_settlement_run_id);

        return $run?->filters['recipient_roles'] ?? null;
    }

    private function release(CommissionSettlement $settlement): void
    {
        CommissionAccrual::query()->where('commission_settlement_id', $settlement->id)->update(['commission_settlement_id' => null]);
    }

    /**
     * The agent document is in and the settlement is not approved yet (spec 0267):
     * the state the supplier link and the approval need.
     */
    public function assertDocumentReceived(CommissionSettlement $settlement): void
    {
        if ($settlement->status !== CommissionSettlementStatus::DocumentReceived) {
            abort(HttpStatusEnum::CONFLICT->value, __('The settlement must have the agent document received.'));
        }
    }

    private function assertDraft(CommissionSettlement $settlement): void
    {
        if ($settlement->status !== CommissionSettlementStatus::Draft) {
            abort(HttpStatusEnum::CONFLICT->value, __('Only a draft settlement can be modified.'));
        }
    }
}
