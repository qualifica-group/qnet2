<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function updateRowLeadsActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    // The MODULE gate (spec 0034): updateRow() is now governed by
    // `import-runs.update` in addition to `leads.import`.
    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// AC-017 — PATCH /api/imports/{domain}/{importRun}/rows/{row}
// ---------------------------------------------------------------------------

it('AC-017: editing an error row re-validates it to valid, flags is_edited and recomputes the run counters', function () {
    $actor = updateRowLeadsActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => ['Email' => 'email', 'Nome' => 'first_name', 'Cognome' => 'last_name'],
        'dedup_strategy' => 'create_new',
    ]);
    $row = ImportRunRow::factory()->error()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'raw_values' => ['Email' => 'not-an-email', 'Nome' => '', 'Cognome' => ''],
        'mapped_values' => ['email' => 'not-an-email'],
        'messages' => ['The email value "not-an-email" is not valid.'],
    ]);
    app(ImportService::class)->recomputeCounts($run->fresh());
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'values' => ['email' => 'fixed@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi'],
    ])->assertOk();

    $response->assertJsonPath('data.row.status', 'valid')
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.values.email', 'fixed@example.com')
        ->assertJsonPath('data.counts.error_rows', 0)
        ->assertJsonPath('data.counts.valid_rows', 1);

    $row->refresh();
    // The reviser re-validates from the EDITED field values merged onto
    // mapped_values (spec 0033 delta D-2026-07-15-placeholder-review-fields)
    // — raw_values (the original file row) is untouched, since an edited
    // field may have no raw column of its own (e.g. a recognizer-derived
    // first_name/last_name split from a single full_name column).
    expect($row->status->value)->toBe('valid')
        ->and($row->is_edited)->toBeTrue()
        ->and($row->mapped_values['email'])->toBe('fixed@example.com')
        ->and($run->fresh()->invalid_rows)->toBe(0)
        ->and($run->fresh()->valid_rows)->toBe(1);
});

it('AC-017: editing an extra (`__extra__`) column value updates extra_values under its original column key', function () {
    $actor = updateRowLeadsActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => ['Email' => 'email', 'Origine Lead' => '__extra__'],
        'dedup_strategy' => 'create_new',
    ]);
    $row = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'raw_values' => ['Email' => 'ok@example.com', 'Origine Lead' => 'Web'],
        'mapped_values' => ['email' => 'ok@example.com'],
        'extra_values' => ['Origine Lead' => 'Web'],
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'values' => ['Origine Lead' => 'Fiera Milano'],
    ])->assertOk()
        ->assertJsonPath('data.row.values.Origine Lead', 'Fiera Milano');

    expect($row->fresh()->extra_values)->toBe(['Origine Lead' => 'Fiera Milano']);
});

it('403 without leads.import', function () {
    $actor = updateRowLeadsActorWith([]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['values' => ['email' => 'x@example.com']])
        ->assertForbidden();
});

it('404 for a row belonging to a different run', function () {
    $actor = updateRowLeadsActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $otherRun = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $rowFromOtherRun = ImportRunRow::factory()->create(['import_run_id' => $otherRun->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$rowFromOtherRun->id}", ['values' => ['email' => 'x@example.com']])
        ->assertNotFound();
});

it('updates a row on a run started by another user (runs are shared)', function () {
    $actor = updateRowLeadsActorWith(['import']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['values' => ['email' => 'x@example.com']])
        ->assertOk();
});

it('422 when the run is not in `reviewing`', function () {
    $actor = updateRowLeadsActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Staging]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['values' => ['email' => 'x@example.com']])
        ->assertStatus(422);
});

it('422 when a `values` key is not mapped nor an extra column on this run', function () {
    $actor = updateRowLeadsActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => ['Email' => 'email'],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'values' => ['not_mapped_field' => 'x'],
    ])->assertStatus(422)->assertJsonValidationErrors('values.not_mapped_field');
});
