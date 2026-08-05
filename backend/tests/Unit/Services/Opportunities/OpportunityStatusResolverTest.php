<?php

use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Services\Opportunities\OpportunityStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (factories, relations), so bind the full TestCase +
// RefreshDatabase explicitly — Pest.php only auto-binds them under Feature/.
uses(TestCase::class, RefreshDatabase::class);

/**
 * Spec 0082, BR-1..BR-4: the computed Opportunity status.
 */
function resolver(): OpportunityStatusResolver
{
    return app(OpportunityStatusResolver::class);
}

// ---------------------------------------------------------------------------
// BR-1/BR-2 — quotes drive the status
// ---------------------------------------------------------------------------

it('collapses quotes sharing one status into a single entry carrying the count (AC-001)', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteStatus::factory()->create(['name' => 'Da approvare', 'color' => 'slate']);
    Quote::factory()->count(3)->create([
        'opportunity_id' => $opportunity->id,
        'quote_status_id' => $status->id,
    ]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['source'])->toBe(OpportunityStatusResolver::SOURCE_QUOTES);
    expect($summary['distinct_count'])->toBe(1);
    expect($summary['entries'])->toBe([[
        'id' => $status->id,
        'name' => 'Da approvare',
        'color' => 'slate',
        'group' => $status->group->value,
        'count' => 3,
    ]]);
});

it('returns one entry per distinct quote status, ordered by sort_order (AC-002)', function () {
    $opportunity = Opportunity::factory()->create();
    $inCorso = QuoteStatus::factory()->create(['name' => 'In corso', 'sort_order' => 30]);
    $inLavorazione = QuoteStatus::factory()->create(['name' => 'In lavorazione', 'sort_order' => 20]);

    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $inCorso->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $inLavorazione->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['distinct_count'])->toBe(2);
    expect(array_column($summary['entries'], 'name'))->toBe(['In lavorazione', 'In corso']);
    expect(array_column($summary['entries'], 'count'))->toBe([1, 1]);
});

it('counts quotes per status independently when the distribution is uneven', function () {
    $opportunity = Opportunity::factory()->create();
    $draft = QuoteStatus::factory()->create(['name' => 'Da approvare', 'sort_order' => 0]);
    $accepted = QuoteStatus::factory()->create(['name' => 'Da firmare', 'sort_order' => 10]);

    Quote::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $draft->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $accepted->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect(array_column($summary['entries'], 'count'))->toBe([2, 1]);
});

// ---------------------------------------------------------------------------
// BR-4 — the workflow-status fallback
// ---------------------------------------------------------------------------

it('falls back to the working state when the opportunity has no quote (AC-003)', function () {
    $workflowStatus = OpportunityWorkflowStatus::factory()->create(['name' => 'Da lavorare', 'color' => 'blue']);
    $opportunity = Opportunity::factory()->create();
    $opportunity->forceFill(['opportunity_workflow_status_id' => $workflowStatus->id])->save();

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['source'])->toBe(OpportunityStatusResolver::SOURCE_WORKFLOW);
    expect($summary['distinct_count'])->toBe(1);
    expect($summary['entries'])->toBe([[
        'id' => $workflowStatus->id,
        'name' => 'Da lavorare',
        'color' => 'blue',
        'group' => $workflowStatus->group->value,
        'count' => 1,
    ]]);
});

it('returns an empty summary with no quote and no working state (AC-004)', function () {
    $opportunity = Opportunity::factory()->create();

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary)->toBe([
        'source' => OpportunityStatusResolver::SOURCE_WORKFLOW,
        'distinct_count' => 0,
        'entries' => [],
    ]);
});

it('ignores the working state as soon as one quote exists', function () {
    $workflowStatus = OpportunityWorkflowStatus::factory()->create(['name' => 'Da lavorare']);
    $opportunity = Opportunity::factory()->create();
    $opportunity->forceFill(['opportunity_workflow_status_id' => $workflowStatus->id])->save();
    $quoteStatus = QuoteStatus::factory()->create(['name' => 'Da approvare']);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $quoteStatus->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['source'])->toBe(OpportunityStatusResolver::SOURCE_QUOTES);
    expect(array_column($summary['entries'], 'name'))->toBe(['Da approvare']);
});

// ---------------------------------------------------------------------------
// Eager-loaded vs bare model: both paths must agree
// ---------------------------------------------------------------------------

it('produces the same summary from an eager-loaded model as from a bare one', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteStatus::factory()->create(['name' => 'Da approvare']);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $status->id]);

    $bare = resolver()->resolve(Opportunity::query()->findOrFail($opportunity->id));
    $eager = resolver()->resolve(
        Opportunity::query()->with(OpportunityStatusResolver::EAGER_LOADS)->findOrFail($opportunity->id),
    );

    expect($eager)->toBe($bare);
});

it('resolves an eager-loaded page without lazy loading (AC-007)', function () {
    $status = QuoteStatus::factory()->create(['name' => 'Da approvare']);
    $opportunities = Opportunity::factory()->count(3)->create();
    foreach ($opportunities as $opportunity) {
        Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_status_id' => $status->id]);
    }

    Opportunity::preventLazyLoading();

    $page = Opportunity::query()->with(OpportunityStatusResolver::EAGER_LOADS)->get();
    $summaries = $page->map(fn (Opportunity $row): array => resolver()->resolve($row))->all();

    Opportunity::preventLazyLoading(false);

    expect($summaries)->toHaveCount(3);
    expect(array_column($summaries, 'distinct_count'))->toBe([1, 1, 1]);
});
