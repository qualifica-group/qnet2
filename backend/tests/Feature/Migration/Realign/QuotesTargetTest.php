<?php

use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\QuotesTarget;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Spec 0375 AC-012 on the offer: fields, managers, status and contract (spec 0372 AC-011, AC-013..016).

/**
 * @param  list<array<string, mixed>>  $records
 */
function realignQuotesTarget(array $records, bool $apply = true): LegacyRealignReport
{
    Http::swap(new HttpFactory);
    fakeLegacyRealign('quotes', array_column($records, 'id'), [], $records);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(QuotesTarget::class)->changedSince('2026-10-23'), '2026-10-23', $apply, $report);

    return $report;
}

/**
 * @return array<string, mixed>
 */
function realignWonContract(array $overrides = []): array
{
    return ['status' => 'scheduled', 'accepted_at' => '2024-04-01', 'validated_at' => '2024-04-02', 'renewal_date' => '2025-04-01', 'terminated_at' => null, ...$overrides];
}

function realignedQuote(int $oldId = 42): Quote
{
    return Quote::query()->where('old_id', $oldId)->firstOrFail();
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->opportunity = Opportunity::factory()->create(['old_id' => 7]);
    $this->category = legacyQuoteServiceProduct(10)->category;
    Activity::query()->delete();
    Notification::fake();
});

it('AC-012: aligns fields and managers, by field in the report, without activity', function () {
    $manager = User::factory()->create(['old_id' => 51, 'name' => 'Ada Rossi']);
    runLegacyQuotesImport([legacyQuoteRecord()]);

    $report = realignQuotesTarget([legacyQuoteRecord(['title' => 'Renamed', 'manager_user_ids' => [51], 'updated_at' => '2026-11-01 09:00:00'])]);

    $quote = realignedQuote();

    expect($quote->title)->toBe('Renamed')
        ->and($quote->managers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($quote->updated_at->toDateTimeString())->toBe('2026-11-01 09:00:00')
        ->and(collect($report->changes)->pluck(4)->all())->toContain('title', 'managers')
        ->and(collect($report->changes)->firstWhere(4, 'managers')[6])->toContain('Ada Rossi')
        ->and(Activity::query()->where('subject_type', (new Quote)->getMorphClass())->count())->toBe(0);

    Notification::assertNothingSent();
});

it('AC-012: an offer won in the legacy becomes won and gets its contract', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);

    $report = realignQuotesTarget([legacyQuoteRecord(['outcome' => 'won', 'contract' => realignWonContract()])]);

    $contract = Contract::query()->where('quote_id', realignedQuote()->id)->sole();

    expect(QuoteWorkflowStatus::query()->whereKey(realignedQuote()->quote_workflow_status_id)->value('system_key'))->toBe('closed_won')
        ->and($contract->contract_status_id)->toBe(ContractStatus::query()->where('name', 'Programmato')->value('id'))
        ->and(collect($report->changes)->pluck(4)->all())->toContain('status', 'contract');
});

it('AC-012: a contract changed on qnet-2 keeps its values while the offer is aligned', function () {
    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'won', 'contract' => realignWonContract()])]);
    $contract = Contract::query()->where('quote_id', realignedQuote()->id)->sole();
    activity()->performedOn($contract)->event('updated')->log('updated');

    $report = realignQuotesTarget([legacyQuoteRecord(['title' => 'Renamed', 'outcome' => 'won', 'contract' => realignWonContract(['renewal_date' => '2026-04-01'])])]);

    expect(realignedQuote()->title)->toBe('Renamed')
        ->and($contract->fresh()->renewal_date->toDateString())->toBe('2025-04-01')
        ->and(collect($report->messages)->pluck(3)->all())->toContain('Contract changed in qnet-2 after the import; not updated.');
});

it('AC-012: an activity on the opportunity protects the offer', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);
    activity()->performedOn($this->opportunity)->event('updated')->log('updated');

    $report = realignQuotesTarget([legacyQuoteRecord(['title' => 'Renamed'])]);

    expect(realignedQuote()->title)->toBe('Legacy offer')
        ->and($report->counters['quotes'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1)
        ->and(array_slice($report->touched[0], 6))->toBe(['title', 'Legacy offer', 'Renamed']);
});

it('lists the [OLD] status it needs, and a simulation of two offers does not trip on the rollback (D-16)', function () {
    runLegacyQuotesImport([legacyQuoteRecord(), legacyQuoteRecord(['id' => 43, 'lines' => [['id' => 901, 'product_id' => 10, 'description' => null, 'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => null, 'commissions' => []]]])]);
    $count = QuoteWorkflowStatus::query()->count();

    $report = realignQuotesTarget([
        legacyQuoteRecord(['outcome' => 'stand_by']),
        legacyQuoteRecord(['id' => 43, 'outcome' => 'stand_by', 'lines' => [['id' => 901, 'product_id' => 10, 'description' => null, 'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => null, 'commissions' => []]]]),
    ], apply: false);

    expect($report->counters['quotes'][LegacyRealignReport::FAILED])->toBe(0)
        ->and(QuoteWorkflowStatus::query()->count())->toBe($count)
        ->and(array_keys($report->configurations))->toBe(['[OLD] Stand-by']);
});

it('fails loudly when the date is missing, instead of treating every line as old', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);

    expect(fn () => app(QuotesTarget::class)->realign(realignedQuote(), legacyQuoteRecord(), new LegacyRealignNotes))
        ->toThrow(LogicException::class, 'changedSince');
});

it('receives the date from the runner, so a run with no explicit date aligns the offer', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);
    Http::swap(new HttpFactory);
    fakeLegacyRealign('quotes', [42], [], [legacyQuoteRecord(['title' => 'Renamed'])]);
    $report = new LegacyRealignReport;

    app(LegacyRealignRunner::class)->run(app(QuotesTarget::class), '2026-10-23', true, $report);

    expect($report->counters['quotes'][LegacyRealignReport::FAILED])->toBe(0)
        ->and(realignedQuote()->title)->toBe('Renamed');
});
