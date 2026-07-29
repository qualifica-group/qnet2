<?php

use App\Enums\ImportStatus;
use App\Jobs\AnalyzeImportJob;
use App\Jobs\ProcessStagedImportJob;
use App\Jobs\StageImportJob;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Grants the domain `leads.*` abilities requested, PLUS (spec 0034) the full
 * `import-runs.*` MODULE set whenever `import` is requested — this single
 * actor drives the ENTIRE wizard flow test below (upload/show/configure/rows/
 * updateRow/summary/confirm/index), each now gated by a DIFFERENT
 * `import-runs.*` ability, so granting the full set once is simpler and just
 * as fail-closed as granting each action's ability individually (a
 * fine-grained module-gate/domain-gate split is exercised separately, see the
 * dedicated read/write gate tests).
 *
 * @param  array<int, string>  $abilities
 */
function leadsImportActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['viewAny', 'view', 'create', 'update']);
    }

    return $user;
}

/**
 * The frozen `leads` wizard column_mapping (spec 0033 data_contract) shared
 * by every test below: 3 mapped fields + 1 `__extra__` column.
 *
 * @return array<string, string>
 */
function leadsWizardColumnMapping(): array
{
    return [
        'Email' => 'email',
        'Nome' => 'first_name',
        'Cognome' => 'last_name',
        'Note Extra' => '__extra__',
    ];
}

/**
 * @return array<int, array{name: string, index: int, duplicate: bool}>
 */
function leadsWizardDetectedColumns(): array
{
    return [
        ['name' => 'Email', 'index' => 0, 'duplicate' => false],
        ['name' => 'Nome', 'index' => 1, 'duplicate' => false],
        ['name' => 'Cognome', 'index' => 2, 'duplicate' => false],
        ['name' => 'Note Extra', 'index' => 3, 'duplicate' => false],
    ];
}

// ---------------------------------------------------------------------------
// AC-015 — analyze -> configure -> rows -> updateRow -> summary -> confirm
// ---------------------------------------------------------------------------

it('AC-015: the full leads wizard flow responds with the envelope and expected status at every step', function () {
    Storage::fake('local');
    Queue::fake();
    $actor = leadsImportActorWith(['import']);
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    // Step 1: POST /api/imports/leads -> 201 analyzing.
    $upload = $this->postJson('/api/imports/leads', [
        'file' => UploadedFile::fake()->create('leads.csv', 10, 'text/csv'),
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.import_run.status', 'analyzing')
        ->assertJsonPath('data.import_run.resource', 'leads');

    Queue::assertPushed(AnalyzeImportJob::class);

    /** @var ImportRun $run */
    $run = ImportRun::query()->findOrFail($upload->json('data.import_run.id'));

    // Simulate AnalyzeImportJob's effect (Queue::fake prevents it running).
    $run->update([
        'detected_columns' => leadsWizardDetectedColumns(),
        'total_rows' => 2,
        'column_mapping' => leadsWizardColumnMapping(),
        'status' => ImportStatus::Configuring,
    ]);

    // Step 2: GET show -> enriched wizard catalogue + live suggested_mapping.
    // detected_columns is re-keyed with its deterministic ColumnAnalysis key
    // (bare name here — no duplicate headers in this fixture); column_mapping/
    // suggested_mapping are BOTH keyed the same way, never by raw name alone.
    $this->getJson("/api/imports/leads/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'configuring')
        ->assertJsonPath('data.import_run.dedup_modes', ['create_new', 'update_existing', 'ignore', 'manual'])
        ->assertJsonPath('data.import_run.detected_columns.0.key', 'Email')
        ->assertJsonPath('data.import_run.detected_columns.0.name', 'Email')
        ->assertJsonPath('data.import_run.suggested_mapping.Email', 'email')
        ->assertJsonPath('data.import_run.global_fields.0.id', 'campaign_id');

    // Step 3: PUT configure -> staging, dispatches StageImportJob.
    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => leadsWizardColumnMapping(),
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_new',
    ])->assertOk()
        ->assertJsonPath('data.import_run.status', 'staging');

    Queue::assertPushed(StageImportJob::class);

    $run->refresh();
    expect($run->column_mapping)->toBe(leadsWizardColumnMapping())
        ->and($run->global_config)->toBe(['campaign_id' => $campaign->id])
        ->and($run->dedup_strategy)->toBe('create_new');

    // Simulate StageImportJob's effect: 1 valid + 1 error staged row.
    $validRow = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'raw_values' => ['Email' => 'mario@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Note Extra' => 'Fiera'],
        'mapped_values' => ['email' => 'mario@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi'],
        'extra_values' => ['Note Extra' => 'Fiera'],
    ]);
    $errorRow = ImportRunRow::factory()->error()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'raw_values' => ['Email' => 'not-an-email', 'Nome' => '', 'Cognome' => '', 'Note Extra' => ''],
        'mapped_values' => ['email' => 'not-an-email'],
        'extra_values' => null,
    ]);
    app(ImportService::class)->recomputeCounts($run->fresh());
    $run->update(['status' => ImportStatus::Reviewing]);

    // Step 4: POST rows -> SSRM page of the 2 staged rows (flat {items,
    // pagination} shape, mirroring tables/{domain}/rows — no data wrapper).
    $this->postJson("/api/imports/leads/{$run->id}/rows", ['startRow' => 0, 'endRow' => 20])
        ->assertOk()
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonCount(2, 'items');

    // Step 5: PATCH rows/{row} -> fix the error row, re-validated to valid.
    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$errorRow->id}", [
        'values' => ['email' => 'fixed@example.com', 'first_name' => 'Fixed', 'last_name' => 'Row'],
    ])->assertOk()
        ->assertJsonPath('data.row.status', 'valid')
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.counts.error_rows', 0)
        ->assertJsonPath('data.counts.valid_rows', 2);

    // Step 6: GET summary -> pre-confirm recap.
    $this->getJson("/api/imports/leads/{$run->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.summary.valid_rows', 2)
        ->assertJsonPath('data.summary.error_rows', 0)
        ->assertJsonPath('data.summary.dedup_strategy', 'create_new')
        ->assertJsonPath('data.summary.extra_fields', ['Note Extra']);

    // Step 7: POST confirm -> processing, dispatches ProcessStagedImportJob.
    $this->postJson("/api/imports/leads/{$run->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'processing')
        ->assertJsonPath('data.import_run.imported_rows', null);

    Queue::assertPushed(ProcessStagedImportJob::class);
    expect($run->fresh()->status)->toBe(ImportStatus::Processing);

    expect($validRow->fresh()->status->value)->toBe('valid');
});

// ---------------------------------------------------------------------------
// AC-015 — authz / ownership / validation errors
// ---------------------------------------------------------------------------

it('403 without leads.import on every wizard endpoint', function () {
    Storage::fake('local');
    Queue::fake();
    $actor = leadsImportActorWith([]);
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);

    // Valid bodies throughout: authorization must be denied BEFORE any
    // validation/state check runs, never masked behind a 422.
    $this->postJson('/api/imports/leads', ['file' => UploadedFile::fake()->create('leads.csv', 5, 'text/csv')])->assertForbidden();
    $this->getJson("/api/imports/leads/{$run->id}")->assertForbidden();
    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'], 'global_config' => ['campaign_id' => $campaign->id], 'dedup_strategy' => 'create_new',
    ])->assertForbidden();
    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertForbidden();
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertForbidden();
    $this->postJson("/api/imports/leads/{$run->id}/confirm")->assertForbidden();
    $this->getJson('/api/imports/leads')->assertForbidden();
});

it('404 for a run belonging to another user, on configure/rows/summary/confirm', function () {
    $actor = leadsImportActorWith(['import']);
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $otherUser->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);

    // Valid bodies throughout: ownership must 404 BEFORE any validation/state
    // check runs, never masked behind a 422.
    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'], 'global_config' => ['campaign_id' => $campaign->id], 'dedup_strategy' => 'create_new',
    ])->assertNotFound();
    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertNotFound();
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertNotFound();
    $this->postJson("/api/imports/leads/{$run->id}/confirm")->assertNotFound();
});

it('404 for a run whose resource does not match the route domain', function () {
    $actor = leadsImportActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'business-functions', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertNotFound();
});

it('422 when configure is attempted outside `configuring`, without logging a backend incident', function () {
    $actor = leadsImportActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);
    Log::spy();

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'],
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422);

    // The status guard is a business rule refusing the request, not an
    // incident: it must not reach the error log (nor the Teams alert next to
    // it) — see BaseApiController::handleControllerException.
    Log::shouldNotHaveReceived('error');
});

it('422 when column_mapping targets a field id outside fields()/__ignore__/__extra__', function () {
    $actor = leadsImportActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'not_a_real_field'],
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('column_mapping.Email');
});

it('422 when column_mapping has a key outside the run\'s detected column keys', function () {
    $actor = leadsImportActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Not A Detected Column' => 'email'],
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('column_mapping.Not A Detected Column');
});

it('422 when the required global_config field (campaign_id) is missing', function () {
    $actor = leadsImportActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'],
        'global_config' => [],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('global_config.campaign_id');
});

it('422 when dedup_strategy is outside dedupModes()', function () {
    $actor = leadsImportActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => leadsWizardDetectedColumns(),
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'],
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_only',
    ])->assertStatus(422)->assertJsonValidationErrors('dedup_strategy');
});

it('422 when rows/summary are requested outside `reviewing`', function () {
    $actor = leadsImportActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Staging]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertStatus(422);
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertStatus(422);
});

it('422 when confirm is attempted outside `reviewing`', function () {
    $actor = leadsImportActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm")->assertStatus(422);
});

it('the leads upload accepts an xlsx file (spec 0033)', function () {
    Storage::fake('local');
    Queue::fake();
    $actor = leadsImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $file = UploadedFile::fake()->create('leads.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->postJson('/api/imports/leads', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.import_run.status', 'analyzing');
});

it('two identically-named file columns get distinct ColumnAnalysis keys, both individually mappable', function () {
    Queue::fake();
    $actor = leadsImportActorWith(['import']);
    $campaign = Campaign::factory()->create();
    // "Email" appears at index 0 (bare key "Email") and index 1 (duplicate ->
    // key "Email#1", per ColumnAnalysis::columnKeys()) — the two must never
    // collapse onto the same column_mapping entry.
    $duplicateNamedColumns = [
        ['name' => 'Email', 'index' => 0, 'duplicate' => true],
        ['name' => 'Email', 'index' => 1, 'duplicate' => true],
    ];
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => $duplicateNamedColumns,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.detected_columns.0.key', 'Email')
        ->assertJsonPath('data.import_run.detected_columns.1.key', 'Email#1');

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email', 'Email#1' => '__extra__'],
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_new',
    ])->assertOk();

    expect($run->fresh()->column_mapping)->toBe(['Email' => 'email', 'Email#1' => '__extra__']);
});
