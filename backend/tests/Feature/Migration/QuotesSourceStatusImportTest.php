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

// REQUIREMENT CHANGED (spec 0189 G-29, user decision 2026-10-15): a set
// without the outcome's status gets an [OLD] one instead of leaving the offer open.
it('creates the [OLD] status of the outcome in a set that lacks it, before the closed ones', function (string $outcome, string $name, WorkflowStatusGroup $group) {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['outcome' => $outcome])]);

    $status = importedLegacyQuoteStatus();
    $closed = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->whereNotNull('system_key')->where('system_key', '!=', 'open')->pluck('sort_order');

    expect($status->name)->toBe($name)
        ->and($status->group)->toBe($group)
        ->and($status->quote_workflow_id)->toBeNull()
        ->and($status->system_key)->toBeNull()
        ->and($status->sort_order)->toBeLessThan($closed->min())
        ->and(legacyQuoteWarnings($run))->toBe([]);
})->with([
    'negotiation' => ['negotiation', '[OLD] In trattativa', WorkflowStatusGroup::Pending],
    'call back' => ['call_back', '[OLD] Da Richiamare', WorkflowStatusGroup::Open],
    'stand-by' => ['stand_by', '[OLD] Stand-by', WorkflowStatusGroup::Open],
]);

it('reuses the [OLD] status already created in the set', function () {
    runLegacyQuotesImport([
        legacyQuoteRecord(['outcome' => 'stand_by']),
        legacyQuoteRecord(['id' => 43, 'outcome' => 'stand_by', 'lines' => [[...legacyQuoteRecord()['lines'][0], 'id' => 901]]]),
    ]);

    $ids = Quote::query()->whereIn('old_id', [42, 43])->pluck('quote_workflow_status_id')->unique();

    expect($ids)->toHaveCount(1)
        ->and(QuoteWorkflowStatus::query()->where('name', '[OLD] Stand-by')->count())->toBe(1);
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
