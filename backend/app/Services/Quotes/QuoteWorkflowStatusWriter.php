<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Notes\CreateNoteData;
use App\Enums\WorkflowStatusGroup;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Notes\NoteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The Offerta's working-status advance (spec 0083, T-04): replicates the
 * semantics of App\Services\RequestManagement\RequestWorkflowStatusWriter,
 * re-targeted at the Quote. The ONE choke point every write channel that
 * explicitly changes `quote_workflow_status_id` reaches — QuoteService::
 * create()/update() call it whenever the client submitted an override that
 * differs from the resolver's own baseline.
 *
 * The note a `requires_note` destination demands (AC-023/024/025) is created
 * on the PARENT Opportunity's collaborative-notes thread
 * (`entity_type = 'request-management'`, `entity_id = quote.opportunity_id`),
 * scoped to THIS Offerta via `quote_id` (spec 0085, D-1, AC-030).
 */
final class QuoteWorkflowStatusWriter
{
    /**
     * The `notes.notable_types` slug this module reuses (config/notes.php):
     * the SAME entity a request-management status-change note attaches to,
     * so an offer's note lands on its opportunity's one collaborative
     * thread.
     */
    private const string NOTE_ENTITY_TYPE = 'request-management';

    /**
     * Spec 0102, D-3: the destination `group`s that gate a transition on at
     * least one REVENUE line — every group that closes or validates the
     * Offerta's outcome. `open`/`pending` are deliberately absent (spec 0102
     * scope/out): a rinvio within the working phase never demands one.
     *
     * @var list<WorkflowStatusGroup>
     */
    private const array LINE_REQUIRED_GROUPS = [
        WorkflowStatusGroup::ClosedWon,
        WorkflowStatusGroup::ClosedLost,
        WorkflowStatusGroup::Validated,
    ];

    public function __construct(
        private readonly QuoteWorkflowResolver $workflowResolver,
        private readonly NoteService $noteService,
    ) {}

    /**
     * Mutates $quote->quote_workflow_status_id IN MEMORY (the caller saves):
     * AC-026 — a resend of the current status is not an advance, no
     * requirement scatters; AC-021 — the target must belong to the set
     * QuoteWorkflowResolver resolves for THIS offer right now; spec 0102
     * D-3/AC-010-012/017 — a `group` in LINE_REQUIRED_GROUPS demands at
     * least one REVENUE line, checked BEFORE the note so a rejection never
     * leaves one orphaned (AC-019); AC-023/024/025 — a `requires_note`
     * destination demands a note, and creating it (inside the CALLER's
     * transaction) requires `notes.create`.
     *
     * @throws ValidationException the target status is outside the resolved workflow (AC-021), or its `group` demands a REVENUE line and none exists (spec 0102 AC-010/011/012), or it `requires_note` and none was given (AC-023)
     * @throws AuthorizationException the actor cannot create the note (AC-024)
     */
    public function apply(Quote $quote, int $newStatusId, User $actor, ?string $note): void
    {
        $currentStatusId = $quote->quote_workflow_status_id;

        if ($currentStatusId === $newStatusId) {
            return; // AC-026: resending the current status is not an advance.
        }

        $allowedIds = $this->workflowResolver->statusesFor($this->workflowResolver->resolve($quote))->pluck('id');

        if (! $allowedIds->contains($newStatusId)) {
            throw ValidationException::withMessages([
                'quote_workflow_status_id' => ["The selected status does not belong to the offer's resolved workflow."],
            ]);
        }

        $targetStatus = QuoteWorkflowStatus::query()->findOrFail($newStatusId);

        $this->assertOfferLineForStatus($quote, $targetStatus);

        if ($targetStatus->requires_note) {
            $this->createStatusChangeNote($quote, $actor, $note);
        }

        $quote->quote_workflow_status_id = $newStatusId;
    }

    /**
     * Spec 0102, D-3/AC-010-015/017: refuses a transition into a closing/
     * validating `group` when the Offerta carries zero REVENUE lines — on
     * EVERY channel, including the inline grid edit
     * (WritesInlineEditableCells::updateCell()) that bypasses every
     * FormRequest, which is why this lives at the writer's own choke point
     * rather than only in a mirror FormRequest check. A FRESH count
     * (`offerLines()->count()`), never a relation the caller might already
     * have loaded earlier in the SAME request — QuoteLineWriter::sync()
     * unsets it after writing, but an eager-loaded read before that would
     * otherwise go stale (AC-016: a same-request line write must be seen).
     *
     * @throws ValidationException the target `group` demands a REVENUE line and none exists
     */
    private function assertOfferLineForStatus(Quote $quote, QuoteWorkflowStatus $targetStatus): void
    {
        if (! in_array($targetStatus->group, self::LINE_REQUIRED_GROUPS, true)) {
            return;
        }

        if ($quote->offerLines()->count() > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'offer_lines' => [__('quotes.offer_line_required_for_status')],
        ]);
    }

    /**
     * @throws ValidationException $note is missing/blank (AC-023)
     * @throws AuthorizationException the actor lacks `notes.create` (AC-024)
     */
    private function createStatusChangeNote(Quote $quote, User $actor, ?string $note): void
    {
        if ($note === null || trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => ['A note is required when moving to this working status.'],
            ]);
        }

        if (! $actor->can('notes.create')) {
            throw new AuthorizationException;
        }

        $this->noteService->create($actor, new CreateNoteData(
            entityType: self::NOTE_ENTITY_TYPE,
            entityId: $quote->opportunity_id,
            body: $note,
            parentId: null,
            quoteId: $quote->id,
            mentionIds: [],
        ));
    }
}
