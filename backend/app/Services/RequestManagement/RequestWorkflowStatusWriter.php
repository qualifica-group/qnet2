<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Quotes\QuoteWorkflowStatusAssigner;
use App\Services\Quotes\QuoteWorkflowStatusWriter;
use Illuminate\Validation\ValidationException;

/**
 * The "Stato di lavorazione" block of RequestManagementService::updateWork()
 * (user directive 2026-08-07), extracted into a writer of its own the way
 * every other block of that panel already is (RequestAttributionWriter,
 * RequestProductLineWriter, RequestOfferLineWriter,
 * RequestClientProfileWriter): the orchestrating service had reached the size
 * ceiling (engineering.md §6) and this is the block the directive of
 * 2026-09-09 adds a rule to.
 *
 * Two entry points, both mirroring their former private counterparts
 * verbatim:
 *
 *  - apply() — the explicit advance the panel/grid submits, delegated to the
 *    SAME choke point the quotes module uses (QuoteWorkflowStatusWriter): it
 *    enforces the resolved set (spec 0083 AC-021), the closing/validating
 *    REVENUE-line gate (spec 0102, D-3) and the note a `requires_note`
 *    destination demands (AC-023/024/025).
 *  - rebaseline() — the re-resolution a replaced classification forces
 *    (user directive 2026-09-08), through QuoteWorkflowStatusAssigner.
 *
 * Both mirror the move into the caller's audit arrays because this module
 * reads the OPPORTUNITY's activity thread (D-9), which the Quote's own model
 * log never reaches. The caller owns the transaction.
 */
final class RequestWorkflowStatusWriter
{
    public function __construct(
        private readonly QuoteWorkflowStatusWriter $statusWriter,
        private readonly QuoteWorkflowStatusAssigner $statusAssigner,
    ) {}

    /**
     * Sparse like every other key of the payload, and `null` is NOT a clear:
     * an Offerta always carries a working state (QuoteService bootstraps it
     * at creation), so "no value submitted" is the only meaning null can have
     * here.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException the target is outside the resolved workflow, the client carries no fiscal identity for a positive close, or the destination requires a note and none was given
     */
    public function apply(Quote $quote, Opportunity $opportunity, User $actor, array $data, array &$changed, array &$old): void
    {
        if (! array_key_exists('quote_workflow_status_id', $data) || $data['quote_workflow_status_id'] === null) {
            return;
        }

        $previousStatusId = $quote->quote_workflow_status_id;

        $this->assertClientFiscalIdentity($quote, $opportunity, (int) $data['quote_workflow_status_id'], $data);

        $this->statusWriter->apply(
            $quote,
            (int) $data['quote_workflow_status_id'],
            $actor,
            $data['note'] ?? null,
        );

        if ($quote->quote_workflow_status_id === $previousStatusId) {
            return;
        }

        // `??=`: a rebaseline earlier in this same PATCH already recorded the
        // status the request STARTED from — the audit entry must keep that
        // one, not the intermediate value the rebaseline produced.
        $old['quote_workflow_status_id'] ??= $previousStatusId;
        $changed['quote_workflow_status_id'] = $quote->quote_workflow_status_id;
        // The projection the panel re-renders from is the relation, not the
        // column: a stale loaded copy would send back the PREVIOUS status.
        $quote->unsetRelation('quoteWorkflowStatus');
    }

    /**
     * Re-resolve the offer's workflow baseline after its classification
     * changed, reporting the move into the caller's audit arrays (D-9).
     * Goes through QuoteWorkflowStatusAssigner with NO submitted id — the
     * same single write-side entry point QuoteService uses — so it stops at
     * the baseline and an explicit client choice still advances FROM it at
     * Step 2-ter.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function rebaseline(Quote $quote, User $actor, array &$changed, array &$old): void
    {
        $previousStatusId = $quote->quote_workflow_status_id;

        $this->statusAssigner->assign($quote, null, null, $actor);

        if ($quote->quote_workflow_status_id === $previousStatusId) {
            return;
        }

        $old['quote_workflow_status_id'] = $previousStatusId;
        $changed['quote_workflow_status_id'] = $quote->quote_workflow_status_id;
        $quote->unsetRelation('quoteWorkflowStatus');
    }

    /**
     * Direttiva utente 2026-09-09: a request may not be closed with a
     * POSITIVE outcome (`closed_won`) while its client carries neither a
     * codice fiscale nor a partita IVA. EITHER of the two satisfies it
     * (decisione utente 2026-09-09): a private client has no VAT number and a
     * company's own `tax_code` IS its eleven-digit code, so demanding both
     * would make one of the two kinds unclosable.
     *
     * Deliberately scoped to THIS module, not to the shared
     * QuoteWorkflowStatusWriter where spec 0102's line gate lives: the
     * directive names Gestione Richieste' two channels — the work panel's
     * PATCH and the grid's inline status cell — and both reach
     * RequestManagementService::updateWork(), while the Offerte module's own
     * form must keep closing on its current rules.
     *
     * Checked BEFORE the delegation, for the same reason spec 0102's gate
     * precedes the note (AC-019): a `requires_note` destination would
     * otherwise answer 403 to an actor without `notes.create` instead of the
     * 422 that actually describes why the transition is refused.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertClientFiscalIdentity(Quote $quote, Opportunity $opportunity, int $newStatusId, array $data): void
    {
        if ($newStatusId === $quote->quote_workflow_status_id) {
            return; // Resending the current status is not a transition.
        }

        $targetStatus = QuoteWorkflowStatus::query()->find($newStatusId);

        if ($targetStatus?->group !== WorkflowStatusGroup::ClosedWon) {
            return;
        }

        if ($this->hasFiscalIdentity($opportunity, $data)) {
            return;
        }

        throw ValidationException::withMessages([
            'client_identity' => [__('request-management.fiscal_identity_required_for_status')],
        ]);
    }

    /**
     * Whether the client's card carries a fiscal identifier ONCE THIS PAYLOAD
     * HAS BEEN APPLIED, not as it is persisted right now: the client block is
     * written at Step 5 of updateWork(), after this gate runs, and both
     * channels can carry it in the very request that changes the status — the
     * panel as the whole `client_identity` card, the grid as one of the
     * sparse `client_*` cells (spec 0055, D-7).
     *
     * Public because UpdateRequestRequest asks the SAME question for the
     * INVARIANT half of the rule (decisione utente 2026-09-09): one predicate,
     * so "either identifier, non-blank" cannot drift between the transition
     * gate and the panel's own save guard.
     *
     * @param  array<string, mixed>  $data
     */
    public function hasFiscalIdentity(Opportunity $opportunity, array $data): bool
    {
        $identity = $data['client_identity'] ?? null;

        if ($identity instanceof CreatePersonalData) {
            return $this->filled($identity->taxCode) || $this->filled($identity->vatNumber);
        }

        // Explicitly loaded, never a bare lazy access: the callers reach this
        // writer with the Opportunity in whatever state their own step left
        // it (Model::preventLazyLoading() outside production).
        $opportunity->loadMissing('registry.personalData');
        $card = $opportunity->registry?->personalData;

        return $this->filled($this->submittedOrPersisted($data, 'client_tax_code', $card?->tax_code))
            || $this->filled($this->submittedOrPersisted($data, 'client_vat_number', $card?->vat_number));
    }

    /**
     * The value the inline cell channel is about to write, or the persisted
     * one when this payload does not address that cell at all.
     *
     * @param  array<string, mixed>  $data
     */
    private function submittedOrPersisted(array $data, string $key, ?string $persisted): ?string
    {
        if (! array_key_exists($key, $data)) {
            return $persisted;
        }

        return $data[$key] === null ? null : (string) $data[$key];
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
