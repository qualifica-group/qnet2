<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Translates between the system statuses Offerte shows and the working
 * statuses of an offer's resolved set (spec 0404 A-D6/A-D7): a system status
 * is offered when at least one working status of the set links it, and
 * picking it moves the offer onto the FIRST of those (sort_order, R-1).
 * Reads QuoteWorkflowResolver's memoized set, so a grid page costs one query
 * per distinct workflow.
 */
final class QuoteSystemStatusMap
{
    public function __construct(private readonly QuoteWorkflowResolver $workflowResolver) {}

    /**
     * The destination working status of each system status offered for
     * $quote, keyed by system status id, in the system statuses' order.
     *
     * @return Collection<int, QuoteWorkflowStatus>
     */
    public function targetsFor(Quote $quote): Collection
    {
        return $this->targetsIn($this->resolvedSet($quote));
    }

    /**
     * What a grid row needs: the system statuses offered, and among them the
     * ones whose destination demands a note (never the current one: picking
     * it again writes nothing).
     *
     * @return array{options: array<int, int>, notes: array<int, int>}
     */
    public function rowOptions(Quote $quote): array
    {
        $set = $this->resolvedSet($quote);
        $targets = $this->targetsIn($set);
        $currentId = $this->currentSystemStatusId($quote, $set);

        return [
            'options' => $targets->keys()->all(),
            'notes' => $targets
                ->filter(static fn (QuoteWorkflowStatus $target, int $systemStatusId): bool => $target->requires_note && $systemStatusId !== $currentId)
                ->keys()
                ->all(),
        ];
    }

    /**
     * The working status $quote moves to when $systemStatusId is picked, or
     * null when its current working status already links it (no advance).
     *
     * @throws ValidationException no working status of the set links it
     */
    public function workflowStatusIdFor(Quote $quote, int $systemStatusId): ?int
    {
        $set = $this->resolvedSet($quote);
        $target = $this->targetsIn($set)->get($systemStatusId);

        if ($target === null) {
            throw ValidationException::withMessages([
                'quote_system_status_id' => [__("No working status of this offer's workflow is linked to the selected system status.")],
            ]);
        }

        return $this->currentSystemStatusId($quote, $set) === $systemStatusId ? null : $target->id;
    }

    /**
     * @return Collection<int, QuoteWorkflowStatus>
     */
    private function resolvedSet(Quote $quote): Collection
    {
        return $this->workflowResolver->statusesFor($this->workflowResolver->resolve($quote));
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $set  ordered by sort_order
     * @return Collection<int, QuoteWorkflowStatus>
     */
    private function targetsIn(Collection $set): Collection
    {
        return $set
            ->filter(static fn (QuoteWorkflowStatus $status): bool => $status->systemStatus !== null)
            ->groupBy('quote_system_status_id')
            ->map(static fn (Collection $linked): QuoteWorkflowStatus => $linked->first())
            ->sort(static fn (QuoteWorkflowStatus $a, QuoteWorkflowStatus $b): int => [$a->systemStatus->sort_order, $a->systemStatus->id] <=> [$b->systemStatus->sort_order, $b->systemStatus->id]);
    }

    /**
     * The system status of $quote's working status as it stands in memory,
     * read off the resolved set when the status belongs to it (no query).
     *
     * @param  Collection<int, QuoteWorkflowStatus>  $set
     */
    private function currentSystemStatusId(Quote $quote, Collection $set): ?int
    {
        $statusId = $quote->quote_workflow_status_id;
        $inSet = $set->firstWhere('id', $statusId);

        if ($inSet !== null) {
            return $inSet->quote_system_status_id;
        }

        return QuoteWorkflowStatus::query()->whereKey($statusId)->value('quote_system_status_id');
    }
}
