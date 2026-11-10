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
    public const string CONTRACT_TOUCHED_WARNING = 'Contract changed in qnet-2 after the import; not updated.';

    public const string CONTRACT_KEPT_WARNING = "The contract's offer is no longer won in the legacy; the contract was kept.";

    private const string CONTRACT_TERMINATED = 'terminated';

    /**
     * Legacy contract status key -> qnet contract status NAME (G-7);
     * `terminated` resolves to the `terminated` system row instead.
     *
     * @var array<string, string>
     */
    private const array CONTRACT_STATUS_NAMES = [
        'to_validate' => 'Da validare',
        'scheduled' => 'Programmato',
        'to_schedule' => 'Da programmare',
        'expiring' => 'In scadenza',
    ];

    public function __construct(
        private readonly LegacyQuoteStatusResolver $resolver,
        private readonly ContractLifecycleManager $lifecycleManager,
        private readonly ContractStatusResolver $contractStatusResolver,
        private readonly LegacyRecordTouchGuard $touchGuard,
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
        $previousStatusId = $this->writeStatus($quote, $target, $state, $warnings);

        // Step 3: a positive close opens and aligns the contract.
        if ($target->group === WorkflowStatusGroup::ClosedWon) {
            $this->lifecycleManager->syncOnStatusChange($quote, $previousStatusId);
            $this->alignContract($quote, $state, $warnings);
        }
    }

    /**
     * Realigns an already imported offer to the legacy state (spec 0372, D-4/D-14): the
     * status follows the legacy outcome (`open` leaves it as is) and the contract is
     * aligned to the legacy dates and status, writing only what differs. A contract
     * worked on in qnet-2 is not aligned; a contract whose offer is no longer won in
     * the legacy is kept as is, never suspended nor deleted.
     *
     * @param  array<int, string>  $warnings
     * @return bool true only when something was written
     */
    public function realign(Quote $quote, LegacyQuoteState $state, array &$warnings): bool
    {
        // Step 1: the target row inside the Quote's own set (null = status unchanged).
        $target = $this->resolver->target($quote, $state, $warnings);
        $contractExisted = Contract::query()->where('quote_id', $quote->id)->exists();
        $wonInLegacy = LegacyQuoteOutcome::tryFrom($state->outcome) === LegacyQuoteOutcome::Won;
        $wrote = false;

        // Step 2: quiet status write; only a move TO closed-won touches the lifecycle (a
        // move away must not suspend the contract).
        if ($target !== null && $target->id !== $quote->quote_workflow_status_id) {
            $previousStatusId = $this->writeStatus($quote, $target, $state, $warnings);
            $wrote = true;

            if ($target->group === WorkflowStatusGroup::ClosedWon) {
                $this->lifecycleManager->syncOnStatusChange($quote, $previousStatusId);
            } elseif ($contractExisted && ! $wonInLegacy) {
                $warnings[] = self::CONTRACT_KEPT_WARNING;
            }
        }

        // Step 3: the contract of an offer won in the legacy, also when the status did not change.
        if ($wonInLegacy && QuoteWorkflowStatus::query()->whereKey($quote->quote_workflow_status_id)->value('group') === WorkflowStatusGroup::ClosedWon) {
            $wrote = $this->realignContract($quote, $state, $contractExisted, $warnings) || $wrote;
        }

        return $wrote;
    }

    /**
     * @param  array<int, string>  $warnings
     * @return int|null the status the offer had before
     */
    private function writeStatus(Quote $quote, QuoteWorkflowStatus $target, LegacyQuoteState $state, array &$warnings): ?int
    {
        $previousStatusId = $quote->quote_workflow_status_id;
        $quote->forceFill(['quote_workflow_status_id' => $target->id])->saveQuietly();
        $this->reportClosedWithoutLines($quote, $target, $state, $warnings);

        return $previousStatusId;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function realignContract(Quote $quote, LegacyQuoteState $state, bool $contractExisted, array &$warnings): bool
    {
        $contract = Contract::query()->where('quote_id', $quote->id)->first();

        if ($contract === null) {
            return false;
        }

        $contract->forceFill($this->contractAttributes($state, $warnings));

        if (! $contract->isDirty()) {
            return false;
        }

        if ($contractExisted && $this->touchGuard->isContractTouched($contract)) {
            $warnings[] = self::CONTRACT_TOUCHED_WARNING;

            return false;
        }

        $contract->saveQuietly();

        return true;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function reportClosedWithoutLines(Quote $quote, QuoteWorkflowStatus $target, LegacyQuoteState $state, array &$warnings): void
    {
        if (in_array($target->group, [WorkflowStatusGroup::ClosedWon, WorkflowStatusGroup::ClosedLost], true)
            && $quote->offerLines()->doesntExist()) {
            $warnings[] = "Legacy outcome '{$state->outcome}' closed the offer without product lines.";
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

        $contract->forceFill($this->contractAttributes($state, $warnings))->saveQuietly();
    }

    /**
     * The contract columns the legacy state decides; a null legacy status leaves the status as is.
     *
     * @param  array<int, string>  $warnings
     * @return array<string, int|string|null>
     */
    private function contractAttributes(LegacyQuoteState $state, array &$warnings): array
    {
        $attributes = [
            'accepted_at' => $state->acceptedAt?->toDateString(),
            'validated_at' => $state->validatedAt?->toDateString(),
            'renewal_date' => $state->renewalDate?->toDateString(),
            'terminated_at' => $state->terminatedAt?->toDateString(),
        ];

        $statusId = $this->contractStatusId($state->contractStatus, $warnings);

        if ($statusId !== null) {
            $attributes['contract_status_id'] = $statusId;
        }

        return $attributes;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function contractStatusId(?string $key, array &$warnings): ?int
    {
        if ($key === null) {
            return null;
        }

        if ($key === self::CONTRACT_TERMINATED) {
            return $this->contractStatusResolver->systemId(StatusSystemKey::Terminated);
        }

        $name = self::CONTRACT_STATUS_NAMES[$key] ?? null;
        $id = $name === null ? null : ContractStatus::query()->where('name', $name)->value('id');

        if ($id === null) {
            $warnings[] = "Contract status '{$key}' not found; the contract keeps its default status.";

            return null;
        }

        return (int) $id;
    }
}
