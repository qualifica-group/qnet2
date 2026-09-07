<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The closing/validating transition gate (spec 0102, D-3/AC-010..015/018):
 * QuoteWorkflowStatusWriter::apply() refuses a transition into a `group` in
 * {closed_won, closed_lost, validated} when the Offerta carries zero REVENUE
 * lines. This file exercises the Offerte channel (PATCH /api/quotes/
 * {quote}), the SAME choke point QuoteService::update() reaches whenever the
 * client submits a `quote_workflow_status_id` override. The inline-edit
 * bypass and the same-request line+status interaction are the Gestione
 * Richieste panel's own concern (RequestManagementWorkflowStatusLineGateTest).
 */
uses(RefreshDatabase::class);

if (! function_exists('lineGateActor')) {
    function lineGateActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.view', 'quotes.update']);

        return $user;
    }
}

if (! function_exists('lineGateQuote')) {
    /**
     * A quote with no matching active workflow: its resolved set is
     * deterministically the GLOBAL default one, so a status minted with
     * `global()` (or a system row, itself global) always belongs to it.
     * Created via the factory, not POST /api/quotes, so the fixture can
     * freely carry zero offer lines regardless of that endpoint's own
     * creation-time requirement (spec 0102 AC-001/002/003, out of this
     * file's scope).
     */
    function lineGateQuote(): Quote
    {
        return Quote::factory()->create(['opportunity_id' => Opportunity::factory()]);
    }
}

it('AC-010: zero REVENUE lines blocks a transition into closed_won -> 422 on offer_lines, status unchanged', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $closedWon->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('AC-011: zero REVENUE lines blocks a transition into closed_lost -> 422 on offer_lines', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedLost = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_lost')->sole();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $closedLost->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('AC-012: zero REVENUE lines blocks a transition into a validated status -> 422 on offer_lines', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    $originalStatusId = $quote->quote_workflow_status_id;
    // 'validated' is no longer a system_key row (dropped by the 2026-08-07
    // migration): a custom global status carrying that `group` is the only
    // way to mint one.
    $validated = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Validated]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $validated->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('AC-013: the gate does not fire on an open or pending destination', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    $pending = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Pending]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $pending->id])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($pending->id);
});

it('AC-014: a REVENUE line lets a zero-cost offer close won', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    QuoteLine::factory()->for($quote)->create();
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $closedWon->id])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

it('AC-015: COST lines alone do not satisfy the gate -> 422 on offer_lines', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    QuoteLine::factory()->for($quote)->cost()->create();
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $closedWon->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('AC-018: resending the current closed_won status on a zero-line offer is a no-op, not a 422', function () {
    $actor = lineGateActor();
    $quote = lineGateQuote();
    $closedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $quote->forceFill(['quote_workflow_status_id' => $closedWon->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $closedWon->id])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});
