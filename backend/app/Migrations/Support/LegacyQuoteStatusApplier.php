<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\StatusSystemKey;
use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\Contracts\ContractLifecycleManager;
use App\Services\Contracts\ContractStatusResolver;

/**
 * Applies a legacy offer status to a freshly imported Quote (spec 0189,
 * G-6/G-7). The target comes from LegacyQuoteStatusResolver and is written
 * quietly: no status-change log, no notification, no writer note. The
 * writer's revenue-line rule (spec 0102) is not applied: a legacy offer closed
 * without migrated lines keeps its outcome, with a warning. A positive close
 * then goes through ContractLifecycleManager (ContractEligibility, idempotent)
 * and the contract it creates is aligned to the legacy dates and status.
 */
final class LegacyQuoteStatusApplier
{
    private const int LEGACY_TERMINATED = 8;

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
        private readonly LegacyQuoteStatusResolver $resolver,
        private readonly ContractLifecycleManager $lifecycleManager,
        private readonly ContractStatusResolver $contractStatusResolver,
    ) {}

    /**
     * @param  array<int, string>  $warnings
     */
    public function apply(Quote $quote, LegacyQuoteState $state, array &$warnings): void
    {
        // Step 1: the target row inside the Quote's own set (null = stay as created).
        $target = $this->resolver->target($quote, $state, $warnings);

        if ($target === null || $target->id === $quote->quote_workflow_status_id) {
            return;
        }

        // Step 2: quiet write, no status log nor notification.
        $previousStatusId = $quote->quote_workflow_status_id;
        $quote->forceFill(['quote_workflow_status_id' => $target->id])->saveQuietly();
        $this->reportClosedWithoutLines($quote, $target, $state, $warnings);

        // Step 3: a positive close opens and aligns the contract.
        if ($target->group === WorkflowStatusGroup::ClosedWon) {
            $this->lifecycleManager->syncOnStatusChange($quote, $previousStatusId);
            $this->alignContract($quote, $state, $warnings);
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function reportClosedWithoutLines(Quote $quote, QuoteWorkflowStatus $target, LegacyQuoteState $state, array &$warnings): void
    {
        if (in_array($target->group, [WorkflowStatusGroup::ClosedWon, WorkflowStatusGroup::ClosedLost], true)
            && $quote->offerLines()->doesntExist()) {
            $warnings[] = "Legacy status {$state->status} closed the offer without product lines.";
        }
    }

    /**
     * No contract (the branch is not sold under one, ContractEligibility) is
     * not an anomaly: nothing to align.
     *
     * @param  array<int, string>  $warnings
     */
    private function alignContract(Quote $quote, LegacyQuoteState $state, array &$warnings): void
    {
        $contract = Contract::query()->where('quote_id', $quote->id)->first();

        if ($contract === null) {
            return;
        }

        $attributes = [
            'accepted_at' => $state->acceptedAt?->toDateString(),
            'validated_at' => $state->validatedAt?->toDateString(),
            'renewal_date' => $state->renewalDate?->toDateString(),
        ];

        $statusId = $this->contractStatusId($state->status, $warnings);

        if ($statusId !== null) {
            $attributes['contract_status_id'] = $statusId;
        }

        if ($state->status === self::LEGACY_TERMINATED) {
            $attributes['terminated_at'] = $state->declinedAt?->toDateString();
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
