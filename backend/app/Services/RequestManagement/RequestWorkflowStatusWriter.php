<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Notes\CreateNoteData;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use App\Services\Notes\NoteService;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The working-state advance of the request-management panel (spec 0049
 * AC-011, spec 0054 D-5), extracted from RequestManagementService (which
 * orchestrates the whole PATCH — SRP + file-size split per engineering.md
 * §6). It stays the ONE choke point both write channels reach: the work
 * panel's PATCH and the inline-edit engine both go through
 * RequestManagementService::updateWork(), which delegates here.
 */
final class RequestWorkflowStatusWriter
{
    /**
     * The `notes.notable_types` slug this module registers itself under
     * (config/notes.php) — the same entity a status-change note attaches to
     * as the collaborative-notes dialog would (spec 0052/0054 D-5).
     */
    private const string NOTE_ENTITY_TYPE = 'request-management';

    public function __construct(
        private readonly OpportunityWorkflowResolver $workflowResolver,
        private readonly NoteService $noteService,
    ) {}

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException the target status is outside the resolved workflow (AC-011), or it `requires_note` and none was given (spec 0054, D-5)
     * @throws AuthorizationException the actor cannot create the note (D-5)
     */
    public function apply(Opportunity $opportunity, int $newStatusId, User $actor, ?string $note, array &$changed, array &$old): void
    {
        $currentStatusId = $opportunity->opportunity_workflow_status_id;

        if ($currentStatusId === $newStatusId) {
            return; // resending the current status is not an advance (mirrors D-4's callback-instant comparison): no note requirement either.
        }

        // AC-011: the same resolved-workflow membership ValidatesWorkflowStatus
        // already enforces for the panel channel — re-checked here so the
        // inline-edit channel (which never goes through that FormRequest)
        // gets the identical guarantee, never a second/different rule.
        $allowedIds = $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity))->pluck('id');

        if (! $allowedIds->contains($newStatusId)) {
            throw ValidationException::withMessages([
                'opportunity_workflow_status_id' => ["The selected working status does not belong to the opportunity's resolved workflow."],
            ]);
        }

        // Spec 0054, D-5: a genuine advance to a `requires_note` status
        // demands one; the note itself is created via the SAME collaborative-
        // notes mechanism the dialog uses (spec 0052), inside the CALLER's
        // transaction, so a note failure rolls back the status change too
        // (AC-010).
        $targetStatus = OpportunityWorkflowStatus::query()->findOrFail($newStatusId);

        if ($targetStatus->requires_note) {
            $this->createStatusChangeNote($opportunity, $actor, $note);
        }

        $old['opportunity_workflow_status_id'] = $currentStatusId;
        $opportunity->opportunity_workflow_status_id = $newStatusId;
        $changed['opportunity_workflow_status_id'] = $newStatusId;
    }

    /**
     * @throws ValidationException $note is missing/blank (AC-009)
     * @throws AuthorizationException the actor lacks `notes.create` (mirrors NoteController::store())
     */
    private function createStatusChangeNote(Opportunity $opportunity, User $actor, ?string $note): void
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
            entityId: $opportunity->getKey(),
            body: $note,
            parentId: null,
            mentionIds: [],
        ));
    }
}
