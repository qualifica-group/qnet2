<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\StatusSystemKey;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\Contracts\ContractLifecycleManager;
use App\Services\Contracts\ContractStatusResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Applies a legacy offer status to a freshly imported Quote (spec 0189,
 * G-6/G-7). The target is resolved inside the status set the Quote already
 * belongs to (never a fixed id) and written quietly: no status-change log, no
 * notification, no writer note. A positive close then goes through
 * ContractLifecycleManager (ContractEligibility, idempotent) and the contract
 * it creates is aligned to the legacy dates and status.
 */
final class LegacyQuoteStatusApplier
{
    private const int LEGACY_PRESENTED = 0;

    private const int LEGACY_REJECTED = 5;

    private const int LEGACY_ACCEPTED_BY_CLIENT = 6;

    private const int LEGACY_TERMINATED = 8;

    /** Legacy contract statuses that mean the offer was won (G-6). */
    private const array LEGACY_WON = [1, 2, 3, 4, self::LEGACY_TERMINATED];

    /**
     * Legacy contract status -> qnet contract status NAME (G-7); 8 resolves to
     * the `terminated` system row instead.
     *
     * @var array<int, string>
     */
    private const array LEGACY_CONTRACT_STATUS_NAMES = [
        1 => 'Da validare',
        2 => 'Programmato',
        3 => 'Da programmare',
        4 => 'In scadenza',
    ];

    public function __construct(
        private readonly ContractLifecycleManager $lifecycleManager,
        private readonly ContractStatusResolver $contractStatusResolver,
    ) {}

    /**
     * @param  array{accepted_at: ?CarbonImmutable, validated_at: ?CarbonImmutable, renewal_date: ?CarbonImmutable, declined_at: ?CarbonImmutable}  $legacyDates
     * @param  array<int, string>  $warnings
     */
    public function apply(Quote $quote, int $legacyStatus, array $legacyDates, array &$warnings): void
    {
        // Step 1: the target row inside the Quote's own set (null = stay as created).
        $target = $this->targetStatus($quote, $legacyStatus, $warnings);

        if ($target === null) {
            return;
        }

        // Step 2: quiet write, no status log nor notification.
        $previousStatusId = $quote->quote_workflow_status_id;
        $quote->forceFill(['quote_workflow_status_id' => $target->id])->saveQuietly();

        // Step 3: a positive close opens and aligns the contract.
        if ($target->group === WorkflowStatusGroup::ClosedWon) {
            $this->lifecycleManager->syncOnStatusChange($quote, $previousStatusId);
            $this->alignContract($quote, $legacyStatus, $legacyDates, $warnings);
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function targetStatus(Quote $quote, int $legacyStatus, array &$warnings): ?QuoteWorkflowStatus
    {
        if ($legacyStatus === self::LEGACY_PRESENTED) {
            return null;
        }

        $statuses = $this->currentStatusSet($quote);

        $target = match (true) {
            $legacyStatus === self::LEGACY_ACCEPTED_BY_CLIENT => $statuses->firstWhere('group', WorkflowStatusGroup::Pending),
            $legacyStatus === self::LEGACY_REJECTED => $statuses->firstWhere('system_key', WorkflowStatusSystemKey::ClosedLost->value),
            in_array($legacyStatus, self::LEGACY_WON, true) => $statuses->firstWhere('system_key', WorkflowStatusSystemKey::ClosedWon->value),
            default => false,
        };

        if ($target === false) {
            $warnings[] = "Unknown legacy status {$legacyStatus}; the offer was left open.";

            return null;
        }

        if ($target === null) {
            $warnings[] = "The offer's status set has no status for legacy status {$legacyStatus}; the offer was left open.";

            return null;
        }

        // The writer's own rule (spec 0102): a closed outcome needs a revenue line.
        if (in_array($target->group, [WorkflowStatusGroup::ClosedWon, WorkflowStatusGroup::ClosedLost], true)
            && $quote->offerLines()->count() === 0) {
            $warnings[] = "Legacy status {$legacyStatus} needs at least one product line; the offer was left open.";

            return null;
        }

        return $target;
    }

    /**
     * The ordered status set the Quote's current status belongs to: a
     * workflow's own, or the global default one (`quote_workflow_id` null).
     *
     * @return Collection<int, QuoteWorkflowStatus>
     */
    private function currentStatusSet(Quote $quote): Collection
    {
        $workflowId = QuoteWorkflowStatus::query()->whereKey($quote->quote_workflow_status_id)->value('quote_workflow_id');

        return QuoteWorkflowStatus::query()
            ->when(
                $workflowId === null,
                fn ($query) => $query->whereNull('quote_workflow_id'),
                fn ($query) => $query->where('quote_workflow_id', $workflowId),
            )
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * No contract (the branch is not sold under one, ContractEligibility) is
     * not an anomaly: nothing to align.
     *
     * @param  array{accepted_at: ?CarbonImmutable, validated_at: ?CarbonImmutable, renewal_date: ?CarbonImmutable, declined_at: ?CarbonImmutable}  $legacyDates
     * @param  array<int, string>  $warnings
     */
    private function alignContract(Quote $quote, int $legacyStatus, array $legacyDates, array &$warnings): void
    {
        $contract = Contract::query()->where('quote_id', $quote->id)->first();

        if ($contract === null) {
            return;
        }

        $attributes = [
            'accepted_at' => $legacyDates['accepted_at']?->toDateString(),
            'validated_at' => $legacyDates['validated_at']?->toDateString(),
            'renewal_date' => $legacyDates['renewal_date']?->toDateString(),
        ];

        $statusId = $this->contractStatusId($legacyStatus, $warnings);

        if ($statusId !== null) {
            $attributes['contract_status_id'] = $statusId;
        }

        if ($legacyStatus === self::LEGACY_TERMINATED) {
            $attributes['terminated_at'] = $legacyDates['declined_at']?->toDateString();
        }

        $contract->forceFill($attributes)->saveQuietly();
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function contractStatusId(int $legacyStatus, array &$warnings): ?int
    {
        if ($legacyStatus === self::LEGACY_TERMINATED) {
            return $this->contractStatusResolver->systemId(StatusSystemKey::Terminated);
        }

        $name = self::LEGACY_CONTRACT_STATUS_NAMES[$legacyStatus];
        $id = ContractStatus::query()->where('name', $name)->value('id');

        if ($id === null) {
            $warnings[] = "Contract status '{$name}' not found; the contract keeps its default status.";

            return null;
        }

        return (int) $id;
    }
}
