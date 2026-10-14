<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The legacy status mapping of the `quotes` source (spec 0189 G-6, user
// decisions 2026-10-13): the legacy decides the `outcome`, qnet picks the
// status inside the offer's workflow.
// Fixtures in tests/Helpers/QuoteMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    Opportunity::factory()->create(['old_id' => 7]);
    legacyQuoteServiceProduct(10);
});

function importedLegacyQuoteStatus(): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::query()->findOrFail(Quote::query()->where('old_id', 42)->value('quote_workflow_status_id'));
}

function globalLegacyTargetStatus(string $name, WorkflowStatusGroup $group): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::factory()->global()->create(['name' => $name, 'group' => $group, 'sort_order' => 5]);
}

it('moves an offer to the named status of its legacy outcome', function (string $outcome, string $name, WorkflowStatusGroup $group) {
    $target = globalLegacyTargetStatus($name, $group);

    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => $outcome])]);

    expect(importedLegacyQuoteStatus()->id)->toBe($target->id);
})->with([
    'negotiation -> in trattativa' => ['negotiation', 'In trattativa', WorkflowStatusGroup::Pending],
    'call back -> da richiamare' => ['call_back', 'Da Richiamare', WorkflowStatusGroup::Open],
    'stand-by -> rimandata' => ['stand_by', 'Rimandata', WorkflowStatusGroup::Open],
    'stand-by -> in standby' => ['stand_by', 'In Standby', WorkflowStatusGroup::Open],
]);

it('closes lost an offer whose outcome is lost', function () {
    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'lost'])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('closed_lost');
});

it('falls back to the first pending status when the set has no negotiation status', function () {
    $pending = globalLegacyTargetStatus('Attesa firma', WorkflowStatusGroup::Pending);

    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'negotiation'])]);

    expect(importedLegacyQuoteStatus()->id)->toBe($pending->id);
});

it('leaves an offer open with a warning when its set has no status for the outcome', function () {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'call_back'])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('open')
        ->and(legacyQuoteWarnings($run))->toContain("The offer's status set has no status for legacy outcome 'call_back'; the offer was left open.");
});

it('keeps an offer open on the open outcome, without warnings', function () {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'open'])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('open')
        ->and(legacyQuoteWarnings($run))->toBe([]);
});

it('leaves an offer open with a warning on an unknown outcome', function () {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'paused'])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('open')
        ->and(legacyQuoteWarnings($run))->toContain("Unknown legacy outcome 'paused'; the offer was left open.");
});

it('moves an offer accepted by the client to the first validated status when its set has no pending one', function () {
    $validated = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('group', WorkflowStatusGroup::Validated)->orderBy('sort_order')->firstOrFail();

    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'accepted_by_client'])]);

    expect(importedLegacyQuoteStatus()->id)->toBe($validated->id);
});
