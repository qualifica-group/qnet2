<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * The qnet status a legacy offer lands on (spec 0189 G-6/G-18): the legacy
 * decides the `outcome`, this resolves it inside the status set the Quote
 * already belongs to (never a fixed id). A named target is looked up by
 * name, then by group. Null = the offer stays on the status it was created
 * with.
 */
final class LegacyQuoteStatusResolver
{
    /** qnet status names per target, tried in order inside the Quote's set. */
    private const array NEGOTIATION_NAMES = ['In trattativa'];

    private const array CALL_BACK_NAMES = ['Da Richiamare'];

    private const array STAND_BY_NAMES = ['Rimandata', 'In Standby'];

    /**
     * @param  array<int, string>  $warnings
     */
    public function target(Quote $quote, LegacyQuoteState $state, array &$warnings): ?QuoteWorkflowStatus
    {
        $outcome = LegacyQuoteOutcome::tryFrom($state->outcome);

        if ($outcome === null) {
            $warnings[] = "Unknown legacy outcome '{$state->outcome}'; the offer was left open.";

            return null;
        }

        if ($outcome === LegacyQuoteOutcome::Open) {
            return null;
        }

        $statuses = $this->currentStatusSet($quote);

        $target = match ($outcome) {
            LegacyQuoteOutcome::Lost => $this->bySystemKey($statuses, WorkflowStatusSystemKey::ClosedLost),
            LegacyQuoteOutcome::Won => $this->bySystemKey($statuses, WorkflowStatusSystemKey::ClosedWon),
            LegacyQuoteOutcome::Negotiation => $this->byName($statuses, self::NEGOTIATION_NAMES)
                ?? $statuses->firstWhere('group', WorkflowStatusGroup::Pending),
            LegacyQuoteOutcome::CallBack => $this->byName($statuses, self::CALL_BACK_NAMES),
            LegacyQuoteOutcome::StandBy => $this->byName($statuses, self::STAND_BY_NAMES),
            LegacyQuoteOutcome::AcceptedByClient => $statuses->firstWhere('group', WorkflowStatusGroup::Pending)
                ?? $statuses->firstWhere('group', WorkflowStatusGroup::Validated),
        };

        if ($target === null) {
            $warnings[] = "The offer's status set has no status for legacy outcome '{$outcome->value}'; the offer was left open.";
        }

        return $target;
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
