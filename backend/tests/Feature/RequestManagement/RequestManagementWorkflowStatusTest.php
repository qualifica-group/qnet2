<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// "Stato di lavorazione" in Gestione Richieste (user directive 2026-08-07):
// the Offerta's own operational status (spec 0083), advanced from this panel
// through the SAME App\Services\Quotes\QuoteWorkflowStatusWriter the quotes
// endpoints reach — so the resolved-set rule (AC-021) and the mandatory
// transition note (AC-023/024/025/026) hold identically on both channels.
//
// What spec 0083 D-2 removed from this module was the OPPORTUNITY's working
// state, which no longer exists; this one belongs to the record the module IS
// since spec 0086.

uses(RefreshDatabase::class);

if (! function_exists('requestWorkflowActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management abilities, unprefixed
     */
    function requestWorkflowActor(array $abilities = ['view', 'update', 'viewAll'], bool $canCreateNotes = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('requestWorkflowQuote')) {
    /**
     * A request with no offer lines and no matching active workflow: its
     * resolved set is deterministically the GLOBAL default one, so a status
     * minted with `global()` always belongs to it.
     */
    function requestWorkflowQuote(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('requestWorkflowGlobalStatus')) {
    function requestWorkflowGlobalStatus(bool $requiresNote = false): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::factory()->global()->create([
            'requires_note' => $requiresNote,
            'sort_order' => 99,
        ]);
    }
}

// ---------------------------------------------------------------------------
// GET — the triplet the panel's select reads
// ---------------------------------------------------------------------------

it('GET exposes the current status and the full resolved set', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    $target = requestWorkflowGlobalStatus();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/request-management/{$quote->id}")->assertOk();

    expect($response->json('data.quote_workflow_status_id'))->toBe($quote->quote_workflow_status_id)
        ->and($response->json('data.quote_workflow_status.id'))->toBe($quote->quote_workflow_status_id)
        ->and(array_column($response->json('data.quote_workflow_statuses'), 'id'))->toContain($target->id);
});

// ---------------------------------------------------------------------------
// PATCH — the advance itself
// ---------------------------------------------------------------------------

it('PATCH advances the Offerta to a status of the resolved set', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    $target = requestWorkflowGlobalStatus();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
    ])->assertOk();

    expect($response->json('data.quote_workflow_status_id'))->toBe($target->id)
        ->and($response->json('data.quote_workflow_status.name'))->toBe($target->name)
        ->and($quote->fresh()->quote_workflow_status_id)->toBe($target->id);
});

it('PATCH to a status outside the resolved workflow -> 422, status unchanged', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    // Bound to a workflow of its own, so it can never belong to the global
    // default set this request resolves.
    $foreign = QuoteWorkflowStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $foreign->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quote_workflow_status_id');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

// ---------------------------------------------------------------------------
// The mandatory transition note (spec 0083, AC-023/024/026)
// ---------------------------------------------------------------------------

it('PATCH to a requires_note status with no note -> 422 on `note`, status unchanged', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $target = requestWorkflowGlobalStatus(requiresNote: true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('PATCH to a requires_note status WITH a note advances and records the note', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    $target = requestWorkflowGlobalStatus(requiresNote: true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
        'note' => 'Cliente ricontattato, si passa allo stato successivo.',
    ])->assertOk();

    // The note lands on the parent Opportunity's collaborative thread,
    // scoped to THIS Offerta (spec 0085, D-1).
    $note = Note::query()
        ->where('notable_type', Opportunity::make()->getMorphClass())
        ->where('notable_id', $quote->opportunity_id)
        ->sole();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($target->id)
        ->and($note->quote_id)->toBe($quote->id)
        ->and($note->user_id)->toBe($actor->id);
});

it('PATCH resending the CURRENT status is not an advance and demands no note', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    $current = QuoteWorkflowStatus::query()->findOrFail($quote->quote_workflow_status_id);
    $current->update(['requires_note' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $current->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($current->id);
});

it('PATCH without request-management.update -> 403, status unchanged', function () {
    $actor = requestWorkflowActor(['view', 'viewAll']);
    $quote = requestWorkflowQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $target = requestWorkflowGlobalStatus();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $target->id,
    ])->assertForbidden();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

// ---------------------------------------------------------------------------
// Contract lifecycle (spec 0072, BR-1) — this panel is the SECOND write path
// for `quote_workflow_status_id`, so it owes the same automation the
// /api/quotes endpoints do (ContractLifecycleTest covers that channel).
// ---------------------------------------------------------------------------

it('PATCH into a closed_won status creates the Contratto', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    // Spec 0102, AC-010: closed_won is now gated on a REVENUE line.
    QuoteLine::factory()->for($quote)->create();
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    Sanctum::actingAs($actor);

    expect(Contract::where('quote_id', $quote->id)->exists())->toBeFalse();

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    $contract = Contract::where('quote_id', $quote->id)->sole();

    expect($contract->accepted_at->toDateString())->toBe(now()->toDateString())
        ->and($contract->isSuspended())->toBeFalse();
});

it('PATCH back OUT of closed_won suspends the Contratto instead of deleting it', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    // Spec 0102, AC-010: closed_won is now gated on a REVENUE line.
    QuoteLine::factory()->for($quote)->create();
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $open = requestWorkflowGlobalStatus();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    $statusBefore = Contract::where('quote_id', $quote->id)->value('contract_status_id');

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $open->id,
    ])->assertOk();

    $contract = Contract::where('quote_id', $quote->id)->sole();

    expect($contract->isSuspended())->toBeTrue()
        ->and($contract->status_before_suspension_id)->toBe($statusBefore);
});

it('PATCH of another field on an already closed_won request does not duplicate the Contratto', function () {
    $actor = requestWorkflowActor();
    $quote = requestWorkflowQuote($actor);
    // Spec 0102, AC-010: closed_won is now gated on a REVENUE line.
    QuoteLine::factory()->for($quote)->create();
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    expect(Contract::where('quote_id', $quote->id)->count())->toBe(1);
});
