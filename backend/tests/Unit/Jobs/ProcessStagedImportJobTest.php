<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Staging\StagingErrorReporter;
use App\Jobs\ProcessStagedImportJob;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Notifications\ImportCompletedNotification;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\Jobs\Fixtures\FakeWizardImportDefinition;

uses(TestCase::class, RefreshDatabase::class);

function runProcessStagedImportJob(ImportRun $run): void
{
    (new ProcessStagedImportJob($run->id))->handle(
        app(ImportRegistry::class),
        app(ImportService::class),
        app(StagingErrorReporter::class),
    );
}

function processingRun(?User $actor = null): ImportRun
{
    config(['imports.definitions' => ['wizard-widgets' => FakeWizardImportDefinition::class]]);

    return ImportRun::factory()->create([
        'user_id' => ($actor ?? User::factory()->create())->id,
        'resource' => 'wizard-widgets',
        'status' => ImportStatus::Processing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
    ]);
}

// ---------------------------------------------------------------------------
// AC-009 — ProcessStagedImportJob: commit staged rows, isolate, notify once
// ---------------------------------------------------------------------------

it('persists ONLY non-skipped/error staged rows via persistRow, updates counters, moves to completed, notifies once', function () {
    Storage::fake('local');
    Notification::fake();

    $actor = User::factory()->create();
    $run = processingRun($actor);

    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid, 'mapped_values' => ['full_name' => 'Mario Rossi']]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2, 'status' => ImportRowStatus::Warning, 'mapped_values' => ['full_name' => 'Anna Verdi']]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3, 'status' => ImportRowStatus::Error, 'mapped_values' => ['full_name' => 'Should Not Import']]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 4, 'status' => ImportRowStatus::Skipped, 'mapped_values' => ['full_name' => 'Also Should Not Import']]);

    runProcessStagedImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Completed)
        ->and($fresh->imported_rows)->toBe(2)
        ->and($fresh->error_count)->toBe(0)
        ->and($fresh->notified_at)->not->toBeNull();

    expect(BusinessFunction::query()->count())->toBe(2)
        ->and(BusinessFunction::query()->where('name', 'Mario Rossi')->exists())->toBeTrue()
        ->and(BusinessFunction::query()->where('name', 'Anna Verdi')->exists())->toBeTrue()
        ->and(BusinessFunction::query()->where('name', 'Should Not Import')->exists())->toBeFalse()
        ->and(BusinessFunction::query()->where('name', 'Also Should Not Import')->exists())->toBeFalse();

    // The action_url must hit the SPA's `/imports/:runId` route: a
    // `/imports/{resource}/{id}` deep link matches no route at all.
    Notification::assertSentTo(
        $actor,
        ImportCompletedNotification::class,
        fn (ImportCompletedNotification $notification): bool => $notification->toArray($actor)['action_url'] === "/imports/{$run->id}",
    );
});

it('isolates a commit-time failure to its own row without blocking the others', function () {
    Storage::fake('local');
    Notification::fake();

    $run = processingRun();

    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid, 'mapped_values' => ['full_name' => 'Mario Rossi']]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2, 'status' => ImportRowStatus::Valid, 'mapped_values' => ['full_name' => FakeWizardImportDefinition::SENTINEL_FAILING_NAME]]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3, 'status' => ImportRowStatus::Valid, 'mapped_values' => ['full_name' => 'Luca Neri']]);

    runProcessStagedImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Completed)
        ->and($fresh->imported_rows)->toBe(2) // Mario + Luca, NOT Boom
        ->and($fresh->error_count)->toBe(1);

    expect(BusinessFunction::query()->count())->toBe(2)
        ->and(BusinessFunction::query()->where('name', FakeWizardImportDefinition::SENTINEL_FAILING_NAME)->exists())->toBeFalse();

    Storage::disk('local')->assertExists($fresh->error_report_path);
    $report = Storage::disk('local')->get($fresh->error_report_path);
    expect($report)->toContain('Simulated commit-time failure.');
});

it('never sends a second notification once notified_at is already set', function () {
    Storage::fake('local');
    Notification::fake();

    $actor = User::factory()->create();
    $run = processingRun($actor);
    $run->update(['notified_at' => now()->subMinute()]);

    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid, 'mapped_values' => ['full_name' => 'Mario Rossi']]);

    runProcessStagedImportJob($run);

    Notification::assertNotSentTo($actor, ImportCompletedNotification::class);
});

it('moves the run to failed on an unhandled exception (e.g. unknown domain)', function () {
    Storage::fake('local');
    $run = processingRun();
    config(['imports.definitions' => []]); // wizard-widgets NOT registered anymore

    expect(fn () => runProcessStagedImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});
