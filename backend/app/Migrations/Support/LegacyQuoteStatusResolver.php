<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\QuoteWorkflows\WorkflowStatusWriter;
use Illuminate\Support\Collection;

/**
 * The qnet status a legacy offer lands on (spec 0189 G-6/G-18): the legacy
 * decides the `outcome`, this resolves it inside the status set the Quote
 * already belongs to (never a fixed id). A named target is looked up by
 * name, then by group. A set without the status of a negotiation, call-back
 * or stand-by gets an [OLD] one, created once (G-29): the user then moves the
 * offers or renames it. Null = the offer stays on the status it was created
 * with.
 */
final class LegacyQuoteStatusResolver
{
    /** qnet status names per target, tried in order inside the Quote's set. */
    private const array NEGOTIATION_NAMES = ['In trattativa'];

    private const array CALL_BACK_NAMES = ['Da Richiamare'];

    private const array STAND_BY_NAMES = ['Rimandata', 'In Standby'];

    /** [OLD] status created in a set that lacks the outcome's own: [name, group]. */
    private const array OLD_STATUSES = [
        LegacyQuoteOutcome::Negotiation->value => ['[OLD] In trattativa', WorkflowStatusGroup::Pending],
        LegacyQuoteOutcome::CallBack->value => ['[OLD] Da Richiamare', WorkflowStatusGroup::Open],
        LegacyQuoteOutcome::StandBy->value => ['[OLD] Stand-by', WorkflowStatusGroup::Open],
    ];

    public function __construct(private readonly WorkflowStatusWriter $statusWriter) {}

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

        $workflowId = $this->statusSetId($quote);
        $statuses = $this->statusSet($workflowId);

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

        $target ??= $this->oldStatus($statuses, $workflowId, $outcome);

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
     * The set's [OLD] status of $outcome, created on first need; null for an
     * outcome without one.
     *
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     */
    private function oldStatus(Collection $statuses, ?int $workflowId, LegacyQuoteOutcome $outcome): ?QuoteWorkflowStatus
    {
        if (! isset(self::OLD_STATUSES[$outcome->value])) {
            return null;
        }

        [$name, $group] = self::OLD_STATUSES[$outcome->value];

        return $this->byName($statuses, [$name])
            ?? $this->statusWriter->appendCustom($workflowId, $name, $group);
    }

    /**
     * The status set the Quote's current status belongs to: a workflow's
     * own, or the global default one (null).
     */
    private function statusSetId(Quote $quote): ?int
    {
        $workflowId = QuoteWorkflowStatus::query()->whereKey($quote->quote_workflow_status_id)->value('quote_workflow_id');

        return $workflowId === null ? null : (int) $workflowId;
    }

    /**
     * @return Collection<int, QuoteWorkflowStatus>
     */
    private function statusSet(?int $workflowId): Collection
    {
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
