<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * The qnet status a legacy offer lands on (spec 0189 G-6), resolved inside
 * the status set the Quote already belongs to (never a fixed id). A
 * presented offer follows the phase of its legacy opportunity; a named
 * target is looked up by name, then by group. Null = the offer stays on the
 * status it was created with.
 */
final class LegacyQuoteStatusResolver
{
    private const int LEGACY_PRESENTED = 0;

    private const int LEGACY_REJECTED = 5;

    private const int LEGACY_ACCEPTED_BY_CLIENT = 6;

    /** Legacy contract statuses that mean the offer was won (G-6). */
    private const array LEGACY_WON = [1, 2, 3, 4, 8];

    /** Legacy opportunity phases (`fases` ids) that move a presented offer. */
    private const int PHASE_OFFER_SENT = 4;

    private const int PHASE_PRO_FORMA = 5;

    private const int PHASE_LOST = 6;

    private const int PHASE_ACCEPTED = 7;

    private const int PHASE_CALL_BACK = 8;

    private const int PHASE_STAND_BY = 11;

    /** Phases closed on the opportunity: lost when another offer won it, else still negotiating. */
    private const array PHASES_CLOSED_ON_OPPORTUNITY = [self::PHASE_PRO_FORMA, self::PHASE_ACCEPTED];

    /** qnet status names per target, tried in order inside the Quote's set. */
    private const array NEGOTIATION_NAMES = ['In trattativa'];

    private const array CALL_BACK_NAMES = ['Da Richiamare'];

    private const array STAND_BY_NAMES = ['Rimandata', 'In Standby'];

    /**
     * @param  array<int, string>  $warnings
     */
    public function target(Quote $quote, LegacyQuoteState $state, array &$warnings): ?QuoteWorkflowStatus
    {
        $statuses = $this->currentStatusSet($quote);

        return match (true) {
            $state->status === self::LEGACY_PRESENTED => $this->presentedTarget($statuses, $state, $warnings),
            $state->status === self::LEGACY_ACCEPTED_BY_CLIENT => $this->acceptedByClientTarget($statuses, $warnings),
            $state->status === self::LEGACY_REJECTED => $this->required($this->bySystemKey($statuses, WorkflowStatusSystemKey::ClosedLost), $state, $warnings),
            in_array($state->status, self::LEGACY_WON, true) => $this->required($this->bySystemKey($statuses, WorkflowStatusSystemKey::ClosedWon), $state, $warnings),
            default => $this->unknown($state, $warnings),
        };
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     * @param  array<int, string>  $warnings
     */
    private function presentedTarget(Collection $statuses, LegacyQuoteState $state, array &$warnings): ?QuoteWorkflowStatus
    {
        $phase = $state->opportunityPhase;

        if (in_array($phase, self::PHASES_CLOSED_ON_OPPORTUNITY, true) && $state->wonByOtherQuote) {
            $phase = self::PHASE_LOST;
        }

        $target = match ($phase) {
            self::PHASE_LOST => $this->bySystemKey($statuses, WorkflowStatusSystemKey::ClosedLost),
            self::PHASE_OFFER_SENT, self::PHASE_PRO_FORMA, self::PHASE_ACCEPTED => $this->byName($statuses, self::NEGOTIATION_NAMES)
                ?? $statuses->firstWhere('group', WorkflowStatusGroup::Pending),
            self::PHASE_CALL_BACK => $this->byName($statuses, self::CALL_BACK_NAMES),
            self::PHASE_STAND_BY => $this->byName($statuses, self::STAND_BY_NAMES),
            default => false,
        };

        if ($target === false) {
            return null;
        }

        if ($target === null) {
            $warnings[] = "The offer's status set has no status for legacy opportunity phase {$state->opportunityPhase}; the offer was left open.";
        }

        return $target;
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     * @param  array<int, string>  $warnings
     */
    private function acceptedByClientTarget(Collection $statuses, array &$warnings): ?QuoteWorkflowStatus
    {
        $target = $statuses->firstWhere('group', WorkflowStatusGroup::Pending)
            ?? $statuses->firstWhere('group', WorkflowStatusGroup::Validated);

        if ($target === null) {
            $warnings[] = "The offer's status set has no status for legacy status ".self::LEGACY_ACCEPTED_BY_CLIENT.'; the offer was left open.';
        }

        return $target;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function required(?QuoteWorkflowStatus $target, LegacyQuoteState $state, array &$warnings): ?QuoteWorkflowStatus
    {
        if ($target === null) {
            $warnings[] = "The offer's status set has no status for legacy status {$state->status}; the offer was left open.";
        }

        return $target;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function unknown(LegacyQuoteState $state, array &$warnings): null
    {
        $warnings[] = "Unknown legacy status {$state->status}; the offer was left open.";

        return null;
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     */
    private function bySystemKey(Collection $statuses, WorkflowStatusSystemKey $key): ?QuoteWorkflowStatus
    {
        return $statuses->firstWhere('system_key', $key->value);
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     * @param  list<string>  $names
     */
    private function byName(Collection $statuses, array $names): ?QuoteWorkflowStatus
    {
        foreach ($names as $name) {
            $match = $statuses->first(
                static fn (QuoteWorkflowStatus $status): bool => mb_strtolower(trim($status->name)) === mb_strtolower($name),
            );

            if ($match !== null) {
                return $match;
            }
        }

        return null;
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
}
