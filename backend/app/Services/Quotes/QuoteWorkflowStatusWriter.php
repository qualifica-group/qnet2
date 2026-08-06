<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Notes\CreateNoteData;
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
 * (`entity_type = 'request-management'`, `entity_id = quote.opportunity_id`)
 * — spec 0085 will add a direct `quote_id` link on the note itself, not yet
 * here.
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

    public function __construct(
        private readonly QuoteWorkflowResolver $workflowResolver,
        private readonly NoteService $noteService,
    ) {}

    /**
     * Mutates $quote->quote_workflow_status_id IN MEMORY (the caller saves):
     * AC-026 — a resend of the current status is not an advance, no
     * requirement scatters; AC-021 — the target must belong to the set
     * QuoteWorkflowResolver resolves for THIS offer right now; AC-023/024/
     * 025 — a `requires_note` destination demands a note, and creating it
     * (inside the CALLER's transaction) requires `notes.create`.
     *
     * @throws ValidationException the target status is outside the resolved workflow (AC-021), or it `requires_note` and none was given (AC-023)
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

        if ($targetStatus->requires_note) {
            $this->createStatusChangeNote($quote, $actor, $note);
        }

        $quote->quote_workflow_status_id = $newStatusId;
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
            mentionIds: [],
        ));
    }
}
