<?php

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Stubs\StubImportDefinition;

uses(RefreshDatabase::class);

/**
 * Spec 0137: GET /api/imports/{domain}/{importRun} exposes `progress`
 * ({processed, total}) while a run is staging or processing, null otherwise.
 */
function progressRunFor(ImportStatus $status, int $totalRows = 0): ImportRun
{
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);

    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['view']);
    Sanctum::actingAs($actor);

    return ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'status' => $status,
        'total_rows' => $totalRows,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function stageProgressRows(ImportRun $run, int $count, array $attributes = []): void
{
    foreach (range(1, $count) as $ignored) {
        ImportRunRow::factory()->create(['import_run_id' => $run->id, ...$attributes]);
    }
}

it('AC-001: reports staged rows against the analysed total while staging', function () {
    $run = progressRunFor(ImportStatus::Staging, 4);
    stageProgressRows($run, 1);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.progress', ['processed' => 1, 'total' => 4]);
});

it('AC-002: reports committed rows against the persistable ones while processing', function () {
    $run = progressRunFor(ImportStatus::Processing, 4);
    stageProgressRows($run, 1, ['status' => ImportRowStatus::Valid, 'persisted_at' => now()]);
    stageProgressRows($run, 1, ['status' => ImportRowStatus::Warning]);
    stageProgressRows($run, 1, ['status' => ImportRowStatus::Duplicate]);
    stageProgressRows($run, 1, ['status' => ImportRowStatus::Error]);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.progress', ['processed' => 1, 'total' => 3]);
});

it('AC-003: is null outside the staging/processing phases', function (ImportStatus $status) {
    $run = progressRunFor($status, 2);
    stageProgressRows($run, 2, ['persisted_at' => now()]);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.progress', null);
})->with([ImportStatus::Analyzing, ImportStatus::Reviewing, ImportStatus::Completed, ImportStatus::Failed]);

it('AC-003: is null while staging a run with no analysed rows', function () {
    $run = progressRunFor(ImportStatus::Staging, 0);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.progress', null);
});

it('AC-003: never reports more processed rows than the total', function () {
    $run = progressRunFor(ImportStatus::Staging, 2);
    stageProgressRows($run, 3);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.progress', ['processed' => 2, 'total' => 2]);
});
