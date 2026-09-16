<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * An actor for the import module. The dedicated `import-runs.*` set was removed
 * (2026-07-17): reads, writes, delete and export are ALL gated by the lead
 * module's single `leads.import` ability. Pass false for an authenticated actor
 * without it. Ownership (view/delete) is enforced separately by the run's
 * `user_id`.
 */
function leadsImportActor(bool $granted = true): User
{
    Permission::findOrCreate('leads.import');

    $user = User::factory()->create();

    if ($granted) {
        $user->givePermissionTo('leads.import');
    }

    return $user;
}

// ---------------------------------------------------------------------------
// Reads (index/show/rows/summary/errors) require `leads.import`.
// ---------------------------------------------------------------------------

it('show/index succeed with leads.import', function () {
    $actor = leadsImportActor();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")->assertOk();
    $this->getJson('/api/imports/leads')->assertOk();
});

it('show 403 without leads.import', function () {
    $actor = leadsImportActor(granted: false);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")->assertForbidden();
});

it('show 200 for a run started by another user WITH leads.import (runs are shared)', function () {
    $actor = leadsImportActor();
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// Writes (upload/configure/updateRow/confirm) require `leads.import` too — the
// single gate, isolated on `leads` (the only registered import domain).
// ---------------------------------------------------------------------------

it('leads upload 403 without leads.import', function () {
    Queue::fake();
    $actor = leadsImportActor(granted: false);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/leads', [
        'file' => UploadedFile::fake()->create('leads.csv', 5, 'text/csv'),
    ])->assertForbidden();
});

it('leads upload 201 with leads.import', function () {
    Queue::fake();
    $actor = leadsImportActor();
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/leads', [
        'file' => UploadedFile::fake()->create('leads.csv', 5, 'text/csv'),
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// rows/summary readable in reviewing|completed|failed; updateRow strictly
// reviewing-only.
// ---------------------------------------------------------------------------

it('rows/summary return 200 on a completed run (read-only detail view)', function () {
    $actor = leadsImportActor();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Completed,
        'column_mapping' => ['Email' => 'email'],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'mapped_values' => ['email' => 'a@example.com']]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertOk();
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertOk();
});

it('rows/summary return 200 on a failed run (read-only detail view)', function () {
    $actor = leadsImportActor();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Failed]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertOk();
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertOk();
});

it('rows/summary still 422 outside reviewing|completed|failed (e.g. staging)', function () {
    $actor = leadsImportActor();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Staging]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertStatus(422);
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertStatus(422);
});

it('updateRow stays 422 on a completed run (edit only allowed in reviewing)', function () {
    $actor = leadsImportActor();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Completed]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['values' => ['email' => 'x@example.com']])
        ->assertStatus(422);
});
