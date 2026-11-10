<?php

declare(strict_types=1);

namespace App\Services\QuoteWorkflows;

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * Refuses to remove a working status some Offerta is on (spec 0377): alone, from
 * a set, or together with its workflow.
 */
final class QuoteWorkflowStatusUsageGuard
{
    private const int CONFLICT = 409;

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     */
    public function assertUnused(Collection $statuses): void
    {
        $usage = Quote::query()
            ->whereIn('quote_workflow_status_id', $statuses->pluck('id'))
            ->pluck('quote_workflow_status_id')
            ->countBy();

        if ($usage->isEmpty()) {
            return;
        }

        $labels = $statuses
            ->filter(fn (QuoteWorkflowStatus $status): bool => $usage->has($status->id))
            ->map(fn (QuoteWorkflowStatus $status): string => "{$status->name} ({$usage->get($status->id)})")
            ->implode(', ');

        abort(self::CONFLICT, __('These statuses are used by quotes and cannot be deleted: :statuses', ['statuses' => $labels]));
    }
}
