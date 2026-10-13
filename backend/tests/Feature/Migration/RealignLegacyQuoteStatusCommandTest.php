<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// `quotes:realign-legacy-status` — moves the offers imported before the
// 2026-10-13 mapping onto it, touching only those still on the `open` system
// status and untouched since the import. Fixtures in
// tests/Helpers/QuoteMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    Opportunity::factory()->create(['old_id' => 7]);
    legacyQuoteServiceProduct(10);

    // One fake for the whole test, serving whatever the legacy says now
    // (Http::fake keeps the first matching stub): imported with the
    // open outcome, the offer stays open.
    $this->legacyRecords = [legacyQuoteRecord()];
    Http::fake([
        fakeMigrationsBaseUrl().'/quotes*' => fn () => Http::response(['items' => $this->legacyRecords, 'pagination' => ['total' => count($this->legacyRecords)]]),
    ]);
    runLegacyQuotesImport($this->legacyRecords);
    $this->quote = Quote::query()->where('old_id', 42)->sole();
    $this->callBack = QuoteWorkflowStatus::factory()->global()->create(['name' => 'Da Richiamare', 'group' => WorkflowStatusGroup::Open, 'sort_order' => 5]);
});

function fakeLegacyQuotesEndpoint(array $records): void
{
    test()->legacyRecords = $records;
}

it('moves an untouched open offer onto the status of its legacy outcome', function () {
    fakeLegacyQuotesEndpoint([legacyQuoteRecord(['outcome' => 'call_back'])]);

    $this->artisan('quotes:realign-legacy-status')
        ->expectsOutputToContain('1 offer(s) realigned.')
        ->assertSuccessful();

    expect($this->quote->fresh()->quote_workflow_status_id)->toBe($this->callBack->id);
});

it('writes nothing in dry-run', function () {
    fakeLegacyQuotesEndpoint([legacyQuoteRecord(['outcome' => 'call_back'])]);
    $before = $this->quote->quote_workflow_status_id;

    $this->artisan('quotes:realign-legacy-status', ['--dry-run' => true])
        ->expectsOutputToContain('1 offer(s) would be realigned.')
        ->assertSuccessful();

    expect($this->quote->fresh()->quote_workflow_status_id)->toBe($before);
});

it('leaves an offer a user has touched since the import', function () {
    activity()->performedOn($this->quote)->log('updated');
    fakeLegacyQuotesEndpoint([legacyQuoteRecord(['outcome' => 'call_back'])]);
    $before = $this->quote->quote_workflow_status_id;

    $this->artisan('quotes:realign-legacy-status')
        ->expectsOutputToContain('0 offer(s) realigned.')
        ->assertSuccessful();

    expect($this->quote->fresh()->quote_workflow_status_id)->toBe($before);
});

it('leaves an offer no longer on the open system status', function () {
    $this->quote->forceFill(['quote_workflow_status_id' => QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'closed_lost')->value('id')])->saveQuietly();
    fakeLegacyQuotesEndpoint([legacyQuoteRecord(['outcome' => 'call_back'])]);

    $this->artisan('quotes:realign-legacy-status')
        ->expectsOutputToContain('0 offer(s) realigned.')
        ->assertSuccessful();

    expect($this->quote->fresh()->quote_workflow_status_id)->not->toBe($this->callBack->id);
});

it('is idempotent and ignores legacy offers never imported', function () {
    fakeLegacyQuotesEndpoint([
        legacyQuoteRecord(['outcome' => 'call_back']),
        legacyQuoteRecord(['id' => 43, 'outcome' => 'call_back']),
    ]);

    $this->artisan('quotes:realign-legacy-status')->expectsOutputToContain('1 offer(s) realigned.')->assertSuccessful();
    $this->artisan('quotes:realign-legacy-status')->expectsOutputToContain('0 offer(s) realigned.')->assertSuccessful();
});
