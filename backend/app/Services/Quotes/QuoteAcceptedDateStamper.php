<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\WorkflowStatusGroup;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Carbon\CarbonImmutable;

/**
 * Keeps `quotes.accepted_at` in step with the workflow status (spec 0365, D-6).
 * Invoked by the Quote `saving` hook, so every channel that saves the model
 * (service, grid edit, request management, resync) is covered in one place.
 * Quiet saves (legacy import) skip the hook by design.
 */
final class QuoteAcceptedDateStamper
{
    public function stamp(Quote $quote): void
    {
        // Step 1: only a status change can move the acceptance date.
        if (! $quote->isDirty('quote_workflow_status_id')) {
            return;
        }

        // Step 2: compare the won-ness of the old and the new status.
        $wasWon = $this->isWon($quote->getOriginal('quote_workflow_status_id'));
        $isWon = $this->isWon($quote->quote_workflow_status_id);

        // Step 3: entering won stamps today, leaving it clears, won -> won keeps.
        if ($isWon && ! $wasWon) {
            $quote->accepted_at = CarbonImmutable::today();
        } elseif (! $isWon) {
            $quote->accepted_at = null;
        }
    }

    private function isWon(mixed $statusId): bool
    {
        if ($statusId === null) {
            return false;
        }

        return QuoteWorkflowStatus::query()->whereKey($statusId)->value('group') === WorkflowStatusGroup::ClosedWon;
    }
}
