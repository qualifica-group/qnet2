<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('quoteWorkflowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quote-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quote-workflows.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-018 — deleting a workflow re-resolves every Quote that referenced one
// of its statuses; none is left orphaned. Spec 0083: the delete-reassign
// flow now concerns the Offerte, not the Opportunita'.
// ---------------------------------------------------------------------------

it('delete: re-resolves every impacted quote onto the global set, remapped by system_key (AC-018)', function () {
    $actor = quoteWorkflowUserWith(['delete']);

    $source = Source::factory()->create();
    $workflow = QuoteWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $openStatus = QuoteWorkflowStatus::factory()
        ->system('open')
        ->create(['quote_workflow_id' => $workflow->id]);
    $closedStatus = QuoteWorkflowStatus::factory()
        ->system('closed_won')
        ->create(['quote_workflow_id' => $workflow->id]);

    // `source_id` is inherited from the Quote's parent Opportunity (D-7).
    $openOpportunity = Opportunity::factory()->create(['source_id' => $source->id]);
    $openQuote = Quote::factory()->create([
        'opportunity_id' => $openOpportunity->id,
        'quote_workflow_status_id' => $openStatus->id,
    ]);
    $closedOpportunity = Opportunity::factory()->create(['source_id' => $source->id]);
    $closedQuote = Quote::factory()->create([
        'opportunity_id' => $closedOpportunity->id,
        'quote_workflow_status_id' => $closedStatus->id,
    ]);
    $globalOpenBefore = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    // Never referencing the deleted workflow's own statuses (a plain
    // factory row, not run through QuoteService, so this is set explicitly
    // rather than resolver-derived) — must stay untouched.
    $unrelatedOpportunity = Opportunity::factory()->create(['source_id' => null]);
    $unrelatedQuote = Quote::factory()->create([
        'opportunity_id' => $unrelatedOpportunity->id,
        'quote_workflow_status_id' => $globalOpenBefore->id,
    ]);
    $untouchedStatusId = $unrelatedQuote->quote_workflow_status_id;

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-workflows/{$workflow->id}")->assertNoContent();

    $globalOpen = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    $globalClosedWon = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();

    // Both impacted quotes are reassigned BEFORE the workflow's own status
    // rows are deleted (QuoteWorkflowService::delete()'s ordering, since
    // `quotes.quote_workflow_status_id` is restrictOnDelete): the current
    // status' system_key is still readable, so each quote re-maps onto the
    // GLOBAL set's row sharing that SAME system_key — 'open' stays 'open',
    // 'closed_won' stays 'closed_won' — never a blanket fallback to 'open'.
    $this->assertDatabaseHas('quotes', [
        'id' => $openQuote->id,
        'quote_workflow_status_id' => $globalOpen->id,
    ]);
    $this->assertDatabaseHas('quotes', [
        'id' => $closedQuote->id,
        'quote_workflow_status_id' => $globalClosedWon->id,
    ]);

    // A quote never referencing the deleted workflow's statuses is left untouched.
    $this->assertDatabaseHas('quotes', [
        'id' => $unrelatedQuote->id,
        'quote_workflow_status_id' => $untouchedStatusId,
    ]);

    expect(Quote::whereNull('quote_workflow_status_id')->count())->toBe(0);
});

it('delete via table bulk-delete also re-resolves impacted quotes (AC-018)', function () {
    $actor = quoteWorkflowUserWith(['delete', 'viewAny']);

    $source = Source::factory()->create();
    $workflow = QuoteWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $openStatus = QuoteWorkflowStatus::factory()
        ->system('open')
        ->create(['quote_workflow_id' => $workflow->id]);
    QuoteWorkflowStatus::factory()
        ->system('closed_won')
        ->create(['quote_workflow_id' => $workflow->id]);

    $opportunity = Opportunity::factory()->create(['source_id' => $source->id]);
    $quote = Quote::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $openStatus->id,
    ]);

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/quote-workflows/bulk-delete', ['ids' => [$workflow->id]])->assertOk();

    $this->assertDatabaseMissing('quote_workflows', ['id' => $workflow->id]);

    $quote->refresh();
    expect($quote->quote_workflow_status_id)->not->toBeNull();

    $globalOpen = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    expect($quote->quote_workflow_status_id)->toBe($globalOpen->id);
});
