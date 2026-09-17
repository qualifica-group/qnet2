<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Leads\LeadDuplicateMatcher;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagingErrorReporter;
use App\Jobs\ProcessStagedImportJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * spec 0136 (D-3/D-4/D-5) — the scalability side of the leads duplicate
 * match: AC-004 (query count independent of the number of non-matching
 * contacts) and AC-006 (the re-match at commit still merges two brand-new
 * rows of the same file that share an email into one Registry).
 */

/**
 * Seeds `$count` non-matching contacts (spread over their own Registry
 * cards) plus `$count` non-matching fiscal cards, none of which shares any
 * value with the row matched in the AC-004 tests below.
 */
function seedNonMatchingNoise(int $count): void
{
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($registry, 'personable')->create(['tax_code' => null]);

    Contact::factory()->email()->for($card, 'contactable')->count($count)->create();

    PersonalData::factory()->individual()->count($count)->create();
}

// ---------------------------------------------------------------------------
// AC-004 — query count for a non-matching row is independent of noise size
// ---------------------------------------------------------------------------

it('AC-004: LeadDuplicateMatcher::match issues the same number of queries with 5 or 500 non-matching contacts', function () {
    $row = [
        'email' => 'nobody-matches@example.com',
        'mobile' => '+393339999999',
        'tax_code' => 'NOMTCH80A01H501U',
    ];
    $matcher = app(LeadDuplicateMatcher::class);

    seedNonMatchingNoise(5);
    DB::enableQueryLog();
    $matcher->match($row);
    $queriesWith5 = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    seedNonMatchingNoise(500);
    DB::enableQueryLog();
    $matcher->match($row);
    $queriesWith500 = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesWith5)->toBeGreaterThan(0)
        ->and($queriesWith500)->toBe($queriesWith5);
});

// ---------------------------------------------------------------------------
// AC-006 — re-match at commit merges two new same-email rows into one Registry
// ---------------------------------------------------------------------------

it('AC-006: two new rows sharing an email under update_existing resolve to a single Registry on confirm', function () {
    $campaign = Campaign::factory()->create();

    $run = ImportRun::factory()->create([
        'resource' => 'leads',
        'status' => ImportStatus::Processing,
        'dedup_strategy' => ImportDedupMode::UpdateExisting->value,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);

    // Both rows are staged `valid` (no duplicate exists yet at staging time,
    // mirroring StageImportJob's own re-match against an at-the-time-empty
    // database) so ProcessStagedImportJob's persistRow() is the ONLY place
    // that ever sees them collide.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'dup-row@example.com'],
        'duplicate_of_id' => null,
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Bis', 'email' => 'dup-row@example.com'],
        'duplicate_of_id' => null,
    ]);

    (new ProcessStagedImportJob($run->id))->handle(
        app(ImportRegistry::class),
        app(ImportService::class),
        app(StagingErrorReporter::class),
    );

    expect($run->fresh()->status)->toBe(ImportStatus::Completed);

    $definition = app(LeadsImportDefinition::class);
    $match = $definition->resolveDuplicateMatch(['email' => 'dup-row@example.com'], []);

    expect($match['id'])->not->toBeNull();
    expect(Registry::query()->whereKey($match['id'])->count())->toBe(1);

    // Not just "a" Registry exists: the ONLY card carrying that email is the
    // single one both rows collapsed onto.
    expect(
        Contact::query()->where('normalized_value', 'dup-row@example.com')->count()
    )->toBe(1);
});

/**
 * Documents a real behavioral finding (spec 0136 AC-006 says "strategia
 * create only"): under `create_new` — and the legacy `create_only`, which
 * isn't even offered for `leads` (LeadsImportDefinition::dedupModes()) —
 * `LeadsImportDefinition::persistRow()` only reuses the re-matched Registry
 * when `$mode === ImportDedupMode::UpdateExisting`; any other write mode
 * always calls `createRegistry()` regardless of the match. Two brand-new
 * rows sharing an email therefore produce TWO Registries under `create_new`,
 * not one — the "re-match at commit" (D-5) only actually merges rows under
 * `update_existing`. Not a regression introduced by this spec: the
 * `persistRow()` mode-branch is unchanged, out of this microtask's write
 * surface, and this test only pins the existing behavior down.
 */
it('DOCUMENTED: two new rows sharing an email under create_new still produce two Registries', function () {
    $campaign = Campaign::factory()->create();

    $run = ImportRun::factory()->create([
        'resource' => 'leads',
        'status' => ImportStatus::Processing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);

    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'create-new-dup@example.com'],
        'duplicate_of_id' => null,
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Bis', 'email' => 'create-new-dup@example.com'],
        'duplicate_of_id' => null,
    ]);

    (new ProcessStagedImportJob($run->id))->handle(
        app(ImportRegistry::class),
        app(ImportService::class),
        app(StagingErrorReporter::class),
    );

    expect(Contact::query()->where('normalized_value', 'create-new-dup@example.com')->count())->toBe(2);
});
