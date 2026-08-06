<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * PATCH /api/quotes/{quote} — the `requires_note` mandatory-note rule (spec
 * 0083, T-04, AC-023/024/025/026), now enforced on the Offerta rather than
 * Gestione Richieste (replaces the former RequestManagementWorkPanelNoteTest,
 * deleted: the Opportunity has no working-state field left to PATCH). The
 * rule itself lives in App\Services\Quotes\QuoteWorkflowStatusWriter, the ONE
 * choke point QuoteService::update() reaches whenever the client submits an
 * override that differs from the resolver's own baseline.
 */
uses(RefreshDatabase::class);

if (! function_exists('requiresNoteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requiresNoteActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }
        foreach (['view', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('requiresNoteQuote')) {
    /**
     * A quote with no offer lines and no matching active workflow — its
     * resolved set is deterministically the GLOBAL default one, so a status
     * row minted with `global()` always belongs to it.
     */
    function requiresNoteQuote(): Quote
    {
        return Quote::factory()->create(['opportunity_id' => Opportunity::factory()]);
    }
}

if (! function_exists('requiresNoteGlobalStatus')) {
    function requiresNoteGlobalStatus(bool $requiresNote): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::factory()->global()->create([
            'requires_note' => $requiresNote,
            'sort_order' => 99,
        ]);
    }
}

it('AC-023: advancing to a requires_note status with no note -> 422 on `note`, status unchanged', function () {
    $actor = requiresNoteActor(['quotes.update']);
    $quote = requiresNoteQuote();
    $originalStatusId = $quote->quote_workflow_status_id;
    $target = requiresNoteGlobalStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $target->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
    expect(Note::query()->count())->toBe(0);
});

it('AC-023: advancing to a requires_note status with a blank note -> 422, status unchanged', function () {
    $actor = requiresNoteActor(['quotes.update']);
    $quote = requiresNoteQuote();
    $originalStatusId = $quote->quote_workflow_status_id;
    $target = requiresNoteGlobalStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
        'note' => '   ',
    ])->assertStatus(422)->assertJsonValidationErrors('note');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('AC-024: the note fails (actor lacks notes.create) -> 403, status change rolls back too', function () {
    $actor = requiresNoteActor(['quotes.update']);
    $quote = requiresNoteQuote();
    $originalStatusId = $quote->quote_workflow_status_id;
    $target = requiresNoteGlobalStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
        'note' => 'Attempted note without permission.',
    ])->assertForbidden();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
    expect(Note::query()->count())->toBe(0);
});

it('AC-025: advancing to a requires_note status WITH a valid note -> 200, status changes and a note is created on the parent Opportunity thread', function () {
    $actor = requiresNoteActor(['quotes.update', 'notes.create', 'request-management.view', 'request-management.viewAll']);
    $quote = requiresNoteQuote();
    $target = requiresNoteGlobalStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
        'note' => 'Client confirmed the new commercial terms.',
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($target->id);

    $note = Note::query()
        ->where('notable_type', Opportunity::make()->getMorphClass())
        ->where('notable_id', $quote->opportunity_id)
        ->sole();

    expect($note->body)->toBe('Client confirmed the new commercial terms.')
        ->and($note->user_id)->toBe($actor->id);
});

it('AC-026: resubmitting the SAME status requires no note, even when it requires_note', function () {
    $actor = requiresNoteActor(['quotes.update']);
    $quote = requiresNoteQuote();
    $current = requiresNoteGlobalStatus(true);
    $quote->forceFill(['quote_workflow_status_id' => $current->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $current->id])
        ->assertOk();

    expect(Note::query()->count())->toBe(0);
});

it('AC-021: a status outside the resolved workflow set -> 422 on `quote_workflow_status_id`', function () {
    $actor = requiresNoteActor(['quotes.update']);
    $quote = requiresNoteQuote();
    $originalStatusId = $quote->quote_workflow_status_id;

    // Belongs to an UNRELATED, non-null (and inactive) workflow — never
    // resolved for a quote with no offer lines and no matching criteria.
    $foreignWorkflow = QuoteWorkflow::factory()->create(['is_active' => false]);
    $foreignStatus = QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $foreignWorkflow->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $foreignStatus->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quote_workflow_status_id');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});
