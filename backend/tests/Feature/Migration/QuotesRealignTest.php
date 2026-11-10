<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\Attribute;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// `quotes` source realignment of the offers already imported (spec 0372, D-4/D-5/D-8/D-9,
// AC-001, AC-011..016). Fixtures mirror QuoteMigrationController::mapQuote of the legacy.

beforeEach(function () {
    seedMigrationsConfig();
    $this->opportunity = Opportunity::factory()->create(['old_id' => 7]);
    $this->category = legacyQuoteServiceProduct(10)->category;
    // The factories log their own creation; an imported record starts without activity (D-6).
    Activity::query()->delete();

    // One fake for the whole test, serving whatever the legacy says now.
    $this->legacyRecords = [];
    Http::fake([
        fakeMigrationsBaseUrl().'/quotes*' => fn () => Http::response(['items' => $this->legacyRecords, 'pagination' => ['total' => count($this->legacyRecords)]]),
    ]);
});

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runQuotesAgainstLegacy(array $records, bool $updateExisting = false): MigrationRun
{
    test()->legacyRecords = $records;

    $run = MigrationRun::factory()->create([
        'user_id' => migrationsSuperAdminActor()->id, 'source' => 'quotes', 'update_existing' => $updateExisting,
    ]);
    runMigrationJobFor($run);

    return $run->fresh();
}

/** Entries on the records the realignment writes (the test's own actor user logs by itself). */
function recordActivityCount(): int
{
    return Activity::query()->whereIn('subject_type', [(new Quote)->getMorphClass(), (new Opportunity)->getMorphClass(), (new Contract)->getMorphClass()])->count();
}

function importedQuote(): Quote
{
    return Quote::query()->where('old_id', 42)->firstOrFail();
}

function wonLegacyContract(array $overrides = []): array
{
    return [
        'status' => 'scheduled',
        'accepted_at' => '2024-04-01',
        'validated_at' => '2024-04-02',
        'renewal_date' => '2025-04-01',
        'terminated_at' => null,
        ...$overrides,
    ];
}

it('AC-001: without update_existing an existing offer is skipped without writes', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord()]);
    $before = importedQuote();

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['title' => 'Changed in legacy', 'description' => 'New'])]);

    expect($run->skipped_rows)->toBe(1)->and($run->updated_rows)->toBe(0)
        ->and(importedQuote()->title)->toBe($before->title)
        ->and(importedQuote()->updated_at->toDateTimeString())->toBe($before->updated_at->toDateTimeString());
});

it('AC-011: an untouched offer gets the differing fields, managers and flexible fields, and nothing else', function () {
    Notification::fake();
    $manager = User::factory()->create(['old_id' => 51]);
    $supervisor = User::factory()->create(['old_id' => 52]);
    $attribute = Attribute::factory()->create(['code' => 'application_filed', 'type' => 'textarea']);
    $this->category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    runQuotesAgainstLegacy([legacyQuoteRecord()]);
    $before = importedQuote();
    $lineIds = $before->offerLines()->pluck('id')->all();
    $activityBefore = recordActivityCount();

    $run = runQuotesAgainstLegacy([legacyQuoteRecord([
        'title' => 'Renamed in legacy',
        'description' => 'New internal notes',
        'quote_date' => '2024-06-10',
        'callback_date' => '2024-07-01 15:30:00',
        'supervisor_user_id' => 52,
        'manager_user_ids' => [51],
        'attribute_values' => ['application_filed' => 'Filed'],
        'updated_at' => '2024-09-09 08:00:00',
    ])], updateExisting: true);

    $quote = importedQuote();

    expect($run->updated_rows)->toBe(1)->and($run->skipped_rows)->toBe(0)->and($run->failed_rows)->toBe(0)
        ->and($quote->title)->toBe('Renamed in legacy')
        ->and($quote->title_is_manual)->toBeTrue()
        ->and($quote->internal_notes)->toBe('New internal notes')
        ->and($quote->quote_date->toDateString())->toBe('2024-06-10')
        ->and($quote->next_callback_at->toDateTimeString())->toBe('2024-07-01 00:00:00')
        ->and($quote->supervisor_id)->toBe($supervisor->id)
        ->and($quote->managers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($quote->operator_id)->toBeNull()
        ->and($quote->attribute_values)->toBe(['application_filed' => 'Filed'])
        ->and($quote->updated_at->toDateTimeString())->toBe('2024-09-09 08:00:00')
        ->and($quote->code)->toBe($before->code)
        ->and($quote->reporter_id)->toBe($before->reporter_id)
        ->and($quote->offerLines()->pluck('id')->all())->toBe($lineIds)
        ->and(recordActivityCount())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('re-arms the callback reminder only when the callback changes', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['callback_date' => '2024-07-01'])]);
    importedQuote()->forceFill(['next_callback_reminded_at' => '2024-07-01 09:00:00'])->saveQuietly();

    runQuotesAgainstLegacy([legacyQuoteRecord(['callback_date' => '2024-07-01', 'title' => 'Other'])], updateExisting: true);
    expect(importedQuote()->next_callback_reminded_at)->not->toBeNull();

    runQuotesAgainstLegacy([legacyQuoteRecord(['callback_date' => '2024-08-01', 'title' => 'Other'])], updateExisting: true);
    expect(importedQuote()->next_callback_reminded_at)->toBeNull();
});

it('skips an identical offer without writes and without mapping warnings', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['supervisor_user_id' => 999])]);
    $before = importedQuote();

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['supervisor_user_id' => 999])], updateExisting: true);

    expect($run->skipped_rows)->toBe(1)->and($run->updated_rows)->toBe(0)
        ->and(legacyQuoteWarnings($run))->toBe([])
        ->and(importedQuote()->updated_at->toDateTimeString())->toBe($before->updated_at->toDateTimeString());
});

it('keeps the legacy title blank as the current one', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord()]);
    $title = importedQuote()->title;

    runQuotesAgainstLegacy([legacyQuoteRecord(['title' => '', 'description' => 'Changed'])], updateExisting: true);

    expect(importedQuote()->title)->toBe($title)->and(importedQuote()->internal_notes)->toBe('Changed');
});

it('AC-012: an offer with an activity entry on its opportunity is left as is, with a warning', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord()]);
    activity()->performedOn($this->opportunity)->log('Edited by hand');
    $before = importedQuote();

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['title' => 'Renamed in legacy'])], updateExisting: true);

    expect($run->skipped_rows)->toBe(1)->and($run->updated_rows)->toBe(0)
        ->and(legacyQuoteWarnings($run))->toBe(['Changed in qnet-2 after the import; not updated.'])
        ->and(importedQuote()->title)->toBe($before->title);
});

it('AC-013: an open offer won in the legacy becomes won and gets its contract, quietly', function () {
    Notification::fake();
    runQuotesAgainstLegacy([legacyQuoteRecord()]);
    $activityBefore = recordActivityCount();

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])], updateExisting: true);

    $contract = Contract::query()->where('quote_id', importedQuote()->id)->sole();

    expect($run->updated_rows)->toBe(1)
        ->and(QuoteWorkflowStatus::query()->whereKey(importedQuote()->quote_workflow_status_id)->value('system_key'))->toBe('closed_won')
        ->and($contract->contract_status_id)->toBe(ContractStatus::query()->where('name', 'Programmato')->value('id'))
        ->and($contract->accepted_at->toDateString())->toBe('2024-04-01')
        ->and($contract->renewal_date->toDateString())->toBe('2025-04-01')
        ->and(recordActivityCount())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('AC-014: a won offer with an untouched contract realigns its dates and status when the offer status is unchanged', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])]);
    $statusBefore = importedQuote()->quote_workflow_status_id;

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract([
        'status' => 'expiring', 'renewal_date' => '2026-04-01',
    ])])], updateExisting: true);

    $contract = Contract::query()->where('quote_id', importedQuote()->id)->sole();

    expect($run->updated_rows)->toBe(1)
        ->and(importedQuote()->quote_workflow_status_id)->toBe($statusBefore)
        ->and($contract->contract_status_id)->toBe(ContractStatus::query()->where('name', 'In scadenza')->value('id'))
        ->and($contract->renewal_date->toDateString())->toBe('2026-04-01');
});

it('skips a won offer whose contract already matches the legacy', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])]);

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])], updateExisting: true);

    expect($run->skipped_rows)->toBe(1)->and($run->updated_rows)->toBe(0)->and(legacyQuoteWarnings($run))->toBe([]);
});

it('AC-015: a touched contract is not realigned while the untouched offer is', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])]);
    $contract = Contract::query()->where('quote_id', importedQuote()->id)->sole();
    activity()->performedOn($contract)->log('Edited by hand');

    $run = runQuotesAgainstLegacy([legacyQuoteRecord([
        'title' => 'Renamed in legacy',
        'outcome' => 'won',
        'contract' => wonLegacyContract(['renewal_date' => '2027-01-01']),
    ])], updateExisting: true);

    expect($run->updated_rows)->toBe(1)
        ->and(importedQuote()->title)->toBe('Renamed in legacy')
        ->and($contract->fresh()->renewal_date->toDateString())->toBe('2025-04-01')
        ->and(legacyQuoteWarnings($run))->toBe(['Contract changed in qnet-2 after the import; not updated.']);
});

it('AC-016: an offer no longer won in the legacy follows it while its contract is kept untouched', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])]);
    $contractBefore = Contract::query()->where('quote_id', importedQuote()->id)->sole();
    $activityBefore = recordActivityCount();

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'lost'])], updateExisting: true);

    $contract = Contract::query()->where('quote_id', importedQuote()->id)->sole();

    expect($run->updated_rows)->toBe(1)
        ->and(QuoteWorkflowStatus::query()->whereKey(importedQuote()->quote_workflow_status_id)->value('group'))->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($contract->contract_status_id)->toBe($contractBefore->contract_status_id)
        ->and($contract->suspended_at)->toBeNull()
        ->and($contract->accepted_at->toDateString())->toBe('2024-04-01')
        ->and(Contract::query()->count())->toBe(1)
        ->and(recordActivityCount())->toBe($activityBefore)
        ->and(legacyQuoteWarnings($run))->toBe(["The contract's offer is no longer won in the legacy; the contract was kept."]);
});

it('leaves the offer status as is when the legacy outcome is open', function () {
    runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'won', 'contract' => wonLegacyContract()])]);
    $statusBefore = importedQuote()->quote_workflow_status_id;

    $run = runQuotesAgainstLegacy([legacyQuoteRecord(['outcome' => 'open', 'contract' => null])], updateExisting: true);

    expect($run->skipped_rows)->toBe(1)
        ->and(importedQuote()->quote_workflow_status_id)->toBe($statusBefore)
        ->and(Contract::query()->sole()->accepted_at->toDateString())->toBe('2024-04-01');
});
