<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The legacy status mapping of the `quotes` source (spec 0189 G-6, user
// decisions 2026-10-13): a presented offer follows its opportunity phase.
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

it('moves a presented offer to the named status of its opportunity phase', function (int $phase, string $name, WorkflowStatusGroup $group) {
    $target = globalLegacyTargetStatus($name, $group);

    runLegacyQuotesImport([legacyQuoteRecord(['status' => 0, 'opportunity_phase' => $phase])]);

    expect(importedLegacyQuoteStatus()->id)->toBe($target->id);
})->with([
    'offer sent -> in trattativa' => [4, 'In trattativa', WorkflowStatusGroup::Pending],
    'call back -> da richiamare' => [8, 'Da Richiamare', WorkflowStatusGroup::Open],
    'stand-by -> rimandata' => [11, 'Rimandata', WorkflowStatusGroup::Open],
    'stand-by -> in standby' => [11, 'In Standby', WorkflowStatusGroup::Open],
    'pro-forma, no other won -> in trattativa' => [5, 'In trattativa', WorkflowStatusGroup::Pending],
    'accepted, no other won -> in trattativa' => [7, 'In trattativa', WorkflowStatusGroup::Pending],
]);

it('closes lost a presented offer on a lost opportunity', function () {
    runLegacyQuotesImport([legacyQuoteRecord(['status' => 0, 'opportunity_phase' => 6])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('closed_lost');
});

it('closes lost a presented offer superseded by another won offer of the opportunity', function (int $phase) {
    globalLegacyTargetStatus('In trattativa', WorkflowStatusGroup::Pending);

    runLegacyQuotesImport([legacyQuoteRecord(['status' => 0, 'opportunity_phase' => $phase, 'won_by_other_quote' => true])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('closed_lost');
})->with(['pro-forma' => [5], 'accepted' => [7]]);

it('falls back to the first pending status when the set has no negotiation status', function () {
    $pending = globalLegacyTargetStatus('Attesa firma', WorkflowStatusGroup::Pending);

    runLegacyQuotesImport([legacyQuoteRecord(['status' => 0, 'opportunity_phase' => 4])]);

    expect(importedLegacyQuoteStatus()->id)->toBe($pending->id);
});

it('leaves a presented offer open with a warning when its set has no status for the phase', function () {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['status' => 0, 'opportunity_phase' => 8])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('open')
        ->and(legacyQuoteWarnings($run))->toContain("The offer's status set has no status for legacy opportunity phase 8; the offer was left open.");
});

it('keeps a presented offer open on a new or unknown opportunity phase', function (?int $phase) {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['status' => 0, 'opportunity_phase' => $phase])]);

    expect(importedLegacyQuoteStatus()->system_key)->toBe('open')
        ->and(legacyQuoteWarnings($run))->toBe([]);
})->with(['new' => [1], 'lead' => [12], 'orphan' => [9], 'missing' => [null]]);

it('moves an offer accepted by the client to the first validated status when its set has no pending one', function () {
    $validated = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('group', WorkflowStatusGroup::Validated)->orderBy('sort_order')->firstOrFail();

    runLegacyQuotesImport([legacyQuoteRecord(['status' => 6])]);

    expect(importedLegacyQuoteStatus()->id)->toBe($validated->id);
});
