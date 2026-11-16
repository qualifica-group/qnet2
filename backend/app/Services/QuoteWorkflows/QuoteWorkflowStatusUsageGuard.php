<?php

declare(strict_types=1);

namespace App\Services\QuoteWorkflows;

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * Refuses to remove a working status some Offerta is on (spec 0377): alone, from
 * a set, or together with its workflow. The message names the Offerte by code
 * so the user can find and move them first.
 */
final class QuoteWorkflowStatusUsageGuard
{
    private const int CONFLICT = 409;

    private const int MAX_LISTED_CODES = 10;

    private const string CODE_SEPARATOR = ', ';

    private const string STATUS_SEPARATOR = '; ';

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     */
    public function assertUnused(Collection $statuses): void
    {
        $codesByStatus = Quote::query()
            ->whereIn('quote_workflow_status_id', $statuses->pluck('id'))
            ->orderBy('id')
            ->get(['code', 'quote_workflow_status_id'])
            ->groupBy('quote_workflow_status_id')
            ->map(fn (Collection $quotes): Collection => $quotes->pluck('code'));

        if ($codesByStatus->isEmpty()) {
            return;
        }

        $labels = $statuses
            ->filter(fn (QuoteWorkflowStatus $status): bool => $codesByStatus->has($status->id))
            ->map(fn (QuoteWorkflowStatus $status): string => $this->statusLabel($status, $codesByStatus->get($status->id)))
            ->implode(self::STATUS_SEPARATOR);

        abort(self::CONFLICT, __('These statuses are used by quotes and cannot be deleted: :statuses', ['statuses' => $labels]));
    }

    /**
     * "Name (count): QUO-1, QUO-2" with at most MAX_LISTED_CODES codes, then how many are left out.
     *
     * @param  Collection<int, string>  $codes
     */
    private function statusLabel(QuoteWorkflowStatus $status, Collection $codes): string
    {
        $listed = $codes->take(self::MAX_LISTED_CODES)->implode(self::CODE_SEPARATOR);
        $hidden = $codes->count() - self::MAX_LISTED_CODES;

        if ($hidden > 0) {
            $listed .= ' '.__('and :count more', ['count' => $hidden]);
        }

        return "{$status->name} ({$codes->count()}): {$listed}";
    }
}
