<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Staging\StagingErrorReporter;
use App\Imports\Support\SpreadsheetReader;
use App\Jobs\StageImportJob;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\Jobs\Fixtures\FakeWizardImportDefinition;

uses(TestCase::class, RefreshDatabase::class);

function runStageImportJob(ImportRun $run): void
{
    (new StageImportJob($run->id))->handle(
        app(ImportRegistry::class),
        app(SpreadsheetReader::class),
        app(ImportService::class),
        app(StagingErrorReporter::class),
    );
}

function stagingRun(array $overrides = []): ImportRun
{
    config(['imports.definitions' => ['wizard-widgets' => FakeWizardImportDefinition::class]]);

    $actor = User::factory()->create();

    return ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'wizard-widgets',
        'status' => ImportStatus::Staging,
        'stored_path' => 'imports/upload.csv',
        'column_mapping' => ['Full Name' => 'full_name', 'Email' => 'email'],
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        ...$overrides,
    ]);
}

// ---------------------------------------------------------------------------
// AC-008 — StageImportJob: every file row -> import_run_rows, counts, no domain writes
// ---------------------------------------------------------------------------

it('stages every row with the correct status/messages/resolved/duplicate_of_id and counts, moves to reviewing', function () {
    Storage::fake('local');
    $csv = "Full Name,Email\n"
        ."Mario Rossi,mario@test.com\n" // valid
        .",invalid-email\n" // error: full_name required, email format invalid
        ."Anna Verdi,lowconf@test.com\n" // warning: low-confidence recognizer
        ."Luca Neri,duplicate@test.com\n"; // duplicate, create_new -> valid
    Storage::disk('local')->put('imports/upload.csv', $csv);

    $run = stagingRun();

    runStageImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Reviewing)
        ->and($fresh->total_rows)->toBe(4)
        ->and($fresh->valid_rows)->toBe(2) // Mario + Luca (duplicate, create_new)
        ->and($fresh->warning_rows)->toBe(1)
        ->and($fresh->invalid_rows)->toBe(1)
        ->and($fresh->duplicate_rows)->toBe(0); // create_new never leaves status=duplicate

    $rows = ImportRunRow::query()->where('import_run_id', $fresh->id)->orderBy('row_number')->get();
    expect($rows)->toHaveCount(4);

    expect($rows[0]->status)->toBe(ImportRowStatus::Valid)
        ->and($rows[0]->mapped_values['full_name'])->toBe('Mario Rossi');

    expect($rows[1]->status)->toBe(ImportRowStatus::Error)
        ->and($rows[1]->messages)->toBe(['full_name is required.', 'email must be a valid address.']);

    expect($rows[2]->status)->toBe(ImportRowStatus::Warning)
        ->and($rows[2]->resolved)->toBe(['domain_hint' => 'test.com']);

    expect($rows[3]->status)->toBe(ImportRowStatus::Valid)
        ->and($rows[3]->duplicate_of_id)->toBe(FakeWizardImportDefinition::DUPLICATE_ID);

    // Staging never creates domain records — only import_run_rows.
    expect(BusinessFunction::query()->count())->toBe(0);
});

it('maps a duplicate row to skipped under the ignore strategy', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "Full Name,Email\nLuca Neri,duplicate@test.com\n");

    $run = stagingRun(['dedup_strategy' => ImportDedupMode::Ignore->value]);

    runStageImportJob($run);

    $row = ImportRunRow::query()->where('import_run_id', $run->id)->first();

    expect($row->status)->toBe(ImportRowStatus::Skipped)
        ->and($run->fresh()->valid_rows)->toBe(0);
});

it('maps a duplicate row to duplicate under the manual strategy', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "Full Name,Email\nLuca Neri,duplicate@test.com\n");

    $run = stagingRun(['dedup_strategy' => ImportDedupMode::Manual->value]);

    runStageImportJob($run);

    $row = ImportRunRow::query()->where('import_run_id', $run->id)->first();

    expect($row->status)->toBe(ImportRowStatus::Duplicate)
        ->and($run->fresh()->duplicate_rows)->toBe(1);
});

it('writes the errors report for staged error/skipped rows', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "Full Name,Email\n,\n");

    $run = stagingRun();

    runStageImportJob($run);

    $fresh = $run->fresh();
    expect($fresh->error_report_path)->not->toBeNull();
    Storage::disk('local')->assertExists($fresh->error_report_path);
});

it('moves the run to failed on an unhandled exception (e.g. unknown domain)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "Full Name,Email\nMario Rossi,mario@test.com\n");

    $run = stagingRun();
    config(['imports.definitions' => []]); // wizard-widgets NOT registered anymore

    expect(fn () => runStageImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});

it('moves the run to failed when the worker kills the job before handle() can catch anything', function () {
    // A queue timeout / exhausted attempts / PHP fatal never reaches the
    // catch in handle(): without failed() the run stays `staging` and the
    // wizard polls it forever (AC-010).
    $run = stagingRun();

    (new StageImportJob($run->id))->failed(new RuntimeException('killed'));

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});

it('leaves a run that already moved past staging untouched when a late failure lands', function () {
    $run = stagingRun(['status' => ImportStatus::Reviewing]);

    (new StageImportJob($run->id))->failed(new RuntimeException('killed'));

    expect($run->fresh()->status)->toBe(ImportStatus::Reviewing);
});
