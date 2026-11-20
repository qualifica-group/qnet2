<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Enums\WorkflowStatusGroup;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\RequestManagement\RequestOperatorWriter;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Task 427463: the requests (Offerte) born from a lead follow the lead's
 * Operatore when it is assigned or changed AFTER the conversion, which
 * otherwise only copies it once (ConvertLeadToOpportunity -> GA2).
 *
 * A request follows only while it still mirrors the lead: its Operatore is
 * empty or still the lead's previous one, and it is not closed. An operator
 * set by hand in Gestione Richieste, or a closed request (its operator is
 * what the statistics count), is never moved. Clearing the lead's operator
 * leaves the requests alone. Same writer, activity entry and notification
 * as the request-management bulk assignment.
 */
final class LeadRequestOperatorFollower
{
    private const array CLOSED_GROUPS = [WorkflowStatusGroup::ClosedWon, WorkflowStatusGroup::ClosedLost];

    public function __construct(
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly AssignmentNotifier $assignmentNotifier,
    ) {}

    /**
     * @param  array<int, int|null>  $previousOperatorByLead  lead id => its operator BEFORE the write
     */
    public function follow(array $previousOperatorByLead): void
    {
        // Step 1: the leads whose operator genuinely moved onto someone.
        $newOperatorByLead = $this->movedOperators($previousOperatorByLead);

        if ($newOperatorByLead === []) {
            return;
        }

        /** @var User|null $actor */
        $actor = Auth::user();

        DB::transaction(function () use ($newOperatorByLead, $previousOperatorByLead, $actor): void {
            // Step 2: each request still following its lead takes the new operator.
            foreach ($this->openRequestsOf(array_keys($newOperatorByLead)) as $quote) {
                $leadId = (int) $quote->opportunity->lead_id;
                $current = $quote->operator_id === null ? null : (int) $quote->operator_id;

                if ($current === null || $current === $previousOperatorByLead[$leadId]) {
                    $this->assignOne($quote, $newOperatorByLead[$leadId], $actor);
                }
            }
        });
    }

    /**
     * @param  array<int, int|null>  $previousOperatorByLead
     * @return array<int, int> lead id => new operator
     */
    private function movedOperators(array $previousOperatorByLead): array
    {
        return Lead::query()
            ->whereIn('id', array_keys($previousOperatorByLead))
            ->whereNotNull('operator_id')
            ->pluck('operator_id', 'id')
            ->map(intval(...))
            ->filter(static fn (int $operatorId, int $leadId): bool => $operatorId !== $previousOperatorByLead[$leadId])
            ->all();
    }

    /**
     * @param  array<int, int>  $leadIds
     * @return Collection<int, Quote>
     */
    private function openRequestsOf(array $leadIds): Collection
    {
        return Quote::query()
            ->with('opportunity')
            ->whereHas('opportunity', static fn (Builder $opportunities) => $opportunities->whereIn('lead_id', $leadIds))
            ->whereDoesntHave('quoteWorkflowStatus', static fn (Builder $statuses) => $statuses->whereIn('group', self::CLOSED_GROUPS))
            ->orderBy('id')
            ->get();
    }

    private function assignOne(Quote $quote, int $operatorId, ?User $actor): void
    {
        $changed = [];
        $old = [];

        $this->operatorWriter->apply($quote, $operatorId, $changed, $old);

        if ($changed === []) {
            return;
        }

        $this->assignmentNotifier->notify($quote, $actor, null, [$operatorId => ManagerPositions::OPERATOR]);

        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Lead operator followed by its requests');
    }
}
