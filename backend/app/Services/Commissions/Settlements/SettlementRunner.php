<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementSkipReason as Skip;
use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\Referent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Launches the settlement of a period (spec 0213, D-6): one DRAFT settlement
 * per (referent, company) pair holding open movements, each pair in its own
 * transaction. A pair that cannot be settled is reported with its reason and
 * its movements stay open (carried to the next run).
 */
final class SettlementRunner
{
    public function __construct(
        private readonly OpenMovementSelector $selector,
        private readonly SettlementComposer $composer,
        private readonly SettlementRunReader $reader,
    ) {}

    public function handle(User $actor, SettlementRunData $data): SettlementRunView
    {
        // Step 1: record the run
        $minAmount = $data->minAmount ?? bcadd((string) config('commissions.settlement_min_amount'), '0', 2);
        $run = CommissionSettlementRun::query()->create([
            'period_from' => $data->periodFrom,
            'period_to' => $data->periodTo,
            'filters' => $data->filters(),
            'min_amount' => $minAmount,
            'settlements_count' => 0,
            'skipped' => [],
            'created_by' => $actor->id,
        ]);

        // Step 2: settle every pair, collecting what is left out
        $created = 0;
        $skipped = [];
        $pairs = $this->selector->pairs($data->periodTo, $data->companyId, $data->referentIds, $data->recipientRoles);

        foreach ($pairs as $pair) {
            $outcome = $this->settlePair($run, $actor, (int) $pair->recipient_id, (int) $pair->company_id, $data, $minAmount);

            if ($outcome instanceof CommissionSettlement) {
                $created++;
            } elseif ($outcome !== null) {
                $skipped[] = $outcome;
            }
        }

        // Step 3: store the outcome on the run
        $run->update(['settlements_count' => $created, 'skipped' => $skipped]);

        return $this->reader->view($run);
    }

    /**
     * @return CommissionSettlement|array<string, mixed>|null the settlement, the skipped entry, or null when the pair has nothing left
     */
    private function settlePair(CommissionSettlementRun $run, User $actor, int $referentId, int $companyId, SettlementRunData $data, string $minAmount): CommissionSettlement|array|null
    {
        return DB::transaction(function () use ($run, $actor, $referentId, $companyId, $data, $minAmount): CommissionSettlement|array|null {
            // Step 1: serialize every writer of this agent, then lock the movements
            if (Referent::query()->whereKey($referentId)->lockForUpdate()->first() === null) {
                return null;
            }

            $movements = $this->selector->lockedForPair($referentId, $companyId, $data->periodTo, $data->recipientRoles);

            if ($movements->isEmpty()) {
                return null;
            }

            // Step 2: balance checks first (cheap, independent of the fiscal data), in the order of D-6
            $totals = MovementTotals::from($movements);
            $reason = $this->balanceSkipReason($referentId, $companyId, $totals, $minAmount);

            // Step 3: fiscal terms and amounts (missing_profile / missing_enasarco_parameters)
            try {
                $attributes = $reason === null
                    ? $this->composer->compose($referentId, $companyId, CarbonImmutable::parse($data->periodTo), $totals)
                    : [];
            } catch (SettlementSkippedException $skipped) {
                $reason = $skipped->reason;
            }

            if ($reason !== null) {
                return ['referent_id' => $referentId, 'company_id' => $companyId, 'reason' => $reason->value, 'taxable_amount' => $totals->taxable];
            }

            // Step 4: create the draft and attach the movements
            $settlement = CommissionSettlement::query()->create([
                ...$attributes,
                'commission_settlement_run_id' => $run->id,
                'company_id' => $companyId,
                'referent_id' => $referentId,
                'status' => CommissionSettlementStatus::Draft,
                'period_from' => $data->periodFrom,
                'period_to' => $data->periodTo,
                'created_by' => $actor->id,
            ]);
            CommissionAccrual::query()->whereIn('id', $movements->modelKeys())->update(['commission_settlement_id' => $settlement->id]);

            return $settlement;
        });
    }

    /**
     * draft_exists, then non_positive, then below_threshold. The profile checks
     * come last on purpose: a pair with nothing worth paying is reported for
     * its balance whether or not its fiscal data are complete.
     */
    private function balanceSkipReason(int $referentId, int $companyId, MovementTotals $totals, string $minAmount): ?Skip
    {
        $draftExists = CommissionSettlement::query()
            ->where('referent_id', $referentId)
            ->where('company_id', $companyId)
            ->where('status', CommissionSettlementStatus::Draft->value)
            ->exists();

        return match (true) {
            $draftExists => Skip::DraftExists,
            bccomp($totals->taxable, '0', 2) <= 0 => Skip::NonPositive,
            bccomp($totals->taxable, $minAmount, 2) < 0 => Skip::BelowThreshold,
            default => null,
        };
    }
}
