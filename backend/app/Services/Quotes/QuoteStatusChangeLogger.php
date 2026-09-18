<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\User;

/**
 * Records a working-status change made from the Offerte module on the PARENT
 * Opportunity's activity trail (user directive 2026-09-18), with the same
 * `attributes`/`old` shape RequestManagementService writes for a change made
 * from Gestione Richieste. `quote_workflow_status_id` is outside Quote's
 * #[Fillable], so the automatic log never sees it; without this entry the
 * report's transition indicators (Potenziali, Associati, Trattative concluse,
 * Invio presa in carico) would miss every change made here.
 */
final class QuoteStatusChangeLogger
{
    private const string DESCRIPTION = 'Quote working status change';

    /**
     * No-op when the status did not actually change.
     */
    public function log(Quote $quote, ?int $previousStatusId, User $actor): void
    {
        $currentStatusId = $quote->quote_workflow_status_id;

        if ($currentStatusId === $previousStatusId) {
            return;
        }

        $opportunity = $quote->opportunity()->firstOrFail();

        activity($opportunity->getTable())
            ->performedOn($opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'attributes' => ['quote_workflow_status_id' => $currentStatusId],
                'old' => ['quote_workflow_status_id' => $previousStatusId],
            ])
            ->log(self::DESCRIPTION);
    }
}
