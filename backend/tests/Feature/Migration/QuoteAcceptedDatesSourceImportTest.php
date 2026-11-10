<?php

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/**
 * `quote-accepted-dates` source (spec 0365, D-7, AC-010). The fixtures mirror the legacy contract
 * of GET /api/v2/migration/quote-accepted-dates: `{ id: quotations.id, accepted_at: "Y-m-d" }`.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyQuoteAcceptedDatesImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/quote-accepted-dates*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'quote-accepted-dates']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function acceptedDateQuote(int $legacyId, string $systemKey): Quote
{
    $quote = Quote::factory()->create();
    $quote->forceFill([
        'old_id' => $legacyId,
        'quote_workflow_status_id' => QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', $systemKey)->value('id'),
    ])->saveQuietly();

    return $quote;
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-010: a won quote without a date takes the legacy one, quietly', function () {
    $quote = acceptedDateQuote(31, 'closed_won');
    Activity::query()->delete();

    $run = runLegacyQuoteAcceptedDatesImport([['id' => 31, 'accepted_at' => '2024-05-17']]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and($quote->fresh()->accepted_at->toDateString())->toBe('2024-05-17')
        ->and(Activity::query()->where('subject_type', $quote->getMorphClass())->count())->toBe(0);
});

it('AC-010: a quote that is not won, or already dated, is skipped untouched', function () {
    $open = acceptedDateQuote(32, 'open');
    $dated = acceptedDateQuote(33, 'closed_won');
    $dated->forceFill(['accepted_at' => '2025-01-02'])->saveQuietly();

    $run = runLegacyQuoteAcceptedDatesImport([
        ['id' => 32, 'accepted_at' => '2024-05-17'],
        ['id' => 33, 'accepted_at' => '2024-05-17'],
    ]);

    expect($run->skipped_rows)->toBe(2)->and($run->created_rows)->toBe(0)
        ->and($open->fresh()->accepted_at)->toBeNull()
        ->and($dated->fresh()->accepted_at->toDateString())->toBe('2025-01-02');
});

it('AC-010: a legacy id not migrated is skipped with a warning, a bad date is reported not repaired', function () {
    $quote = acceptedDateQuote(34, 'closed_won');

    $run = runLegacyQuoteAcceptedDatesImport([
        ['id' => 99, 'accepted_at' => '2024-05-17'],
        ['id' => 34, 'accepted_at' => '17-05-2024'],
    ]);

    expect($run->skipped_rows)->toBe(2)
        ->and(collect($run->report)->where('level', 'warning')->pluck('message')->all())->toHaveCount(2)
        ->and($quote->fresh()->accepted_at)->toBeNull();
});

it('AC-010: a second run changes nothing', function () {
    $quote = acceptedDateQuote(35, 'closed_won');
    $records = [['id' => 35, 'accepted_at' => '2024-05-17']];

    runLegacyQuoteAcceptedDatesImport($records);
    $second = runLegacyQuoteAcceptedDatesImport($records);

    expect($second->skipped_rows)->toBe(1)->and($second->created_rows)->toBe(0)
        ->and($quote->fresh()->accepted_at->toDateString())->toBe('2024-05-17');
});

it('registers quote-accepted-dates after quotes with the quotes dependency', function () {
    $source = app(MigrationRegistry::class)->resolve('quote-accepted-dates');

    expect($source->endpoint())->toBe('quote-accepted-dates')
        ->and(collect($source->dependencies())->mapWithKeys(fn ($dependency) => [$dependency->source => $dependency->required])->all())->toBe(['quotes' => true])
        ->and(MigrationOrder::phaseOf('quote-accepted-dates'))->toBeGreaterThan(MigrationOrder::phaseOf('quotes'));
});
