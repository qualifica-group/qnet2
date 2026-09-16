<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagingErrorReporter;
use App\Jobs\ProcessStagedImportJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use App\Services\ImportService;
use App\Support\Import\ImportRunSummaryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * spec 0036 — resolution side: the PATCH .../resolution endpoint and how the
 * commit phase (ProcessStagedImportJob/LeadsImportDefinition::persistRow)
 * honors it. The matching side (tax_code, lead-level match, revise-time
 * clearing) lives in LeadDuplicateMatchTest.
 */

/**
 * @param  array<string, mixed>  $mapped
 */
function stagedDuplicateLeadRow(ImportRun $run, array $mapped, ?int $duplicateOfId = null, ?string $resolution = null): ImportRunRow
{
    return ImportRunRow::factory()->for($run)->create([
        'mapped_values' => $mapped,
        'status' => $duplicateOfId !== null ? ImportRowStatus::Duplicate : ImportRowStatus::Valid,
        'duplicate_of_id' => $duplicateOfId,
        'resolution' => $resolution,
    ]);
}

function runProcessStagedImportJobForDuplicates(ImportRun $run): void
{
    (new ProcessStagedImportJob($run->id))->handle(
        app(ImportRegistry::class),
        app(ImportService::class),
        app(StagingErrorReporter::class),
    );
}

/**
 * @param  array<int, string>  $abilities
 */
function duplicateResolutionActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// AC-003 — PATCH .../rows/{row}/resolution
// ---------------------------------------------------------------------------

it('AC-003: PATCH resolution on a reviewing duplicate row persists it and returns row+counts', function () {
    $actor = duplicateResolutionActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = stagedDuplicateLeadRow($run, ['email' => 'dup@example.com'], duplicateOfId: 999);
    app(ImportService::class)->recomputeCounts($run->fresh());
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}/resolution", ['resolution' => 'update'])
        ->assertOk();

    $response->assertJsonPath('data.row.resolution', 'update')
        ->assertJsonPath('data.counts.duplicate_rows', 1);

    expect($row->fresh()->resolution->value)->toBe('update');
});

it('AC-003: 422 when the run is not reviewing', function () {
    $actor = duplicateResolutionActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Staging]);
    $row = stagedDuplicateLeadRow($run, ['email' => 'dup@example.com'], duplicateOfId: 999);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}/resolution", ['resolution' => 'skip'])
        ->assertStatus(422);
});

it('AC-003: 422 when the row is not a duplicate', function () {
    $actor = duplicateResolutionActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = stagedDuplicateLeadRow($run, ['email' => 'ok@example.com']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}/resolution", ['resolution' => 'skip'])
        ->assertStatus(422);
});

it('AC-003: 422 when resolution is outside the enum', function () {
    $actor = duplicateResolutionActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = stagedDuplicateLeadRow($run, ['email' => 'dup@example.com'], duplicateOfId: 999);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}/resolution", ['resolution' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('resolution');
});

it('AC-003: resolves a row on a run started by another user (runs are shared)', function () {
    $actor = duplicateResolutionActor(['import']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = stagedDuplicateLeadRow($run, ['email' => 'dup@example.com'], duplicateOfId: 999);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}/resolution", ['resolution' => 'skip'])
        ->assertOk();
});

it('AC-003: 403 without leads.import', function () {
    $actor = duplicateResolutionActor([]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = stagedDuplicateLeadRow($run, ['email' => 'dup@example.com'], duplicateOfId: 999);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}/resolution", ['resolution' => 'skip'])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-004 — confirm honors the per-row resolution
// ---------------------------------------------------------------------------

it('AC-004: skip writes nothing, create makes a brand-new registry+lead, update updates the matched registry/lead', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $existing = Registry::factory()->create(['name' => 'Old Name']);
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create(['first_name' => 'Old', 'last_name' => 'Name']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'dup@example.com']);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Processing,
        'dedup_strategy' => ImportDedupMode::Manual->value,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);

    stagedDuplicateLeadRow($run, ['first_name' => 'Skip', 'last_name' => 'Me', 'email' => 'dup@example.com'], $existing->id, 'skip');
    stagedDuplicateLeadRow($run, ['first_name' => 'Force', 'last_name' => 'Create', 'email' => 'dup@example.com'], $existing->id, 'create');
    stagedDuplicateLeadRow($run, ['first_name' => 'New', 'last_name' => 'Name', 'email' => 'dup@example.com'], $existing->id, 'update');

    runProcessStagedImportJobForDuplicates($run);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(ImportStatus::Completed)
        ->and($fresh->imported_rows)->toBe(2); // create + update, NOT skip

    expect(Registry::query()->count())->toBe(2)
        ->and(Registry::query()->where('name', 'Force Create')->exists())->toBeTrue();

    $existing->refresh();
    expect($existing->personalData->first_name)->toBe('New');

    expect(Lead::query()->where('campaign_id', $campaign->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-005 — unresolved duplicate: no write, not imported, surfaces in summary
// ---------------------------------------------------------------------------

it('AC-005: an unresolved duplicate row is never written, never counted as imported, and surfaces as unresolved', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $existing = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'unresolved@example.com']);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Processing,
        'dedup_strategy' => ImportDedupMode::Manual->value,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    stagedDuplicateLeadRow($run, ['first_name' => 'Un', 'last_name' => 'Resolved', 'email' => 'unresolved@example.com'], $existing->id);

    runProcessStagedImportJobForDuplicates($run);

    expect($run->fresh()->imported_rows)->toBe(0)
        ->and(Registry::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(0);

    $summary = app(ImportRunSummaryBuilder::class)->summary($run->fresh());
    expect($summary['duplicate_resolutions'])->toBe(['skip' => 0, 'create' => 0, 'update' => 0, 'unresolved' => 1]);
});

// ---------------------------------------------------------------------------
// AC-007 — legacy strategies unaffected by spec 0036 (regression, kept
// alongside the full existing suite executed for this change)
// ---------------------------------------------------------------------------

it('AC-007: legacy ignore/create_new dedup strategies persist exactly as before spec 0036', function () {
    $campaign = Campaign::factory()->create();

    $ignoreRun = ImportRun::factory()->create(['resource' => 'leads']);
    $ignoreRow = ImportRunRow::factory()->for($ignoreRun)->create(['mapped_values' => ['first_name' => 'Ign', 'last_name' => 'Ore', 'email' => 'ignore@example.com']]);
    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $ignoreRow, ['campaign_id' => $campaign->id], ImportDedupMode::Ignore->value);
    expect(Registry::query()->count())->toBe(0);

    $createRun = ImportRun::factory()->create(['resource' => 'leads']);
    $createRow = ImportRunRow::factory()->for($createRun)->create(['mapped_values' => ['first_name' => 'Cre', 'last_name' => 'Ate', 'email' => 'create@example.com']]);
    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $createRow, ['campaign_id' => $campaign->id], ImportDedupMode::CreateNew->value);
    expect(Registry::query()->count())->toBe(1)
        ->and(Lead::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});
