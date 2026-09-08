<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/request-management/report/{exportRun} + .../download (spec 0106
// data_contract, AC-002/AC-003-bis/AC-003-ter/AC-003-sexies).

uses(RefreshDatabase::class);

if (! function_exists('reportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function reportActorWith(array $abilities): User
    {
        foreach (['report', 'viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — AC-002 / AC-003-ter / AC-003-sexies
// ---------------------------------------------------------------------------

it('200s with the current status while processing', function () {
    $actor = reportActorWith(['report']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'request-management-report']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonMissingPath('data.export_run.resource')
        ->assertJsonMissingPath('data.export_run.file_path');
});

it('403s without request-management.report', function () {
    $actor = reportActorWith([]);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'request-management-report']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")->assertForbidden();
});

it('404s (never 403) for a run belonging to another user', function () {
    $actor = reportActorWith(['report']);
    $otherUser = User::factory()->create();
    $run = ExportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'request-management-report']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")->assertNotFound();
});

it('404s for a run whose resource is not request-management-report', function () {
    $actor = reportActorWith(['report']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'some-other-domain']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")->assertNotFound();
});

it('reports a failed run without leaving it stuck in processing', function () {
    $actor = reportActorWith(['report']);
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'request-management-report',
        'status' => ExportStatus::Failed,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.export_run.status', 'failed');
});

// ---------------------------------------------------------------------------
// download — AC-003-bis / AC-003-ter
// ---------------------------------------------------------------------------

it('streams the CSV with the right Content-Type and attachment filename when completed (AC-003-bis)', function () {
    $actor = reportActorWith(['report']);
    Storage::fake('local');
    Storage::disk('local')->put('exports/run.csv', "\xEF\xBB\xBFCategoria,GA2\n");

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'request-management-report',
        'status' => ExportStatus::Completed,
        'format' => ExportFormat::Csv,
        'original_filename' => 'request-management-report-2026-09-01_2026-09-30.csv',
        'file_path' => 'exports/run.csv',
        'row_count' => 6,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->get("/api/request-management/report/{$run->id}/download")->assertOk();

    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('request-management-report-2026-09-01_2026-09-30.csv');

    expect(Storage::disk('local')->get('exports/run.csv'))->toStartWith("\xEF\xBB\xBF");
});

it('404s when the run has not completed yet', function () {
    $actor = reportActorWith(['report']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'request-management-report']);
    Sanctum::actingAs($actor);

    $this->get("/api/request-management/report/{$run->id}/download")->assertNotFound();
});

it('404s when the completed run has no file on disk', function () {
    $actor = reportActorWith(['report']);
    Storage::fake('local');

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'request-management-report',
        'status' => ExportStatus::Completed,
        'file_path' => 'exports/missing.csv',
    ]);
    Sanctum::actingAs($actor);

    $this->get("/api/request-management/report/{$run->id}/download")->assertNotFound();
});

it('404s (never 403) downloading a run belonging to another user', function () {
    $actor = reportActorWith(['report']);
    $otherUser = User::factory()->create();
    $run = ExportRun::factory()->create([
        'user_id' => $otherUser->id,
        'resource' => 'request-management-report',
        'status' => ExportStatus::Completed,
        'file_path' => 'exports/run.csv',
    ]);
    Sanctum::actingAs($actor);

    $this->get("/api/request-management/report/{$run->id}/download")->assertNotFound();
});

it('403s downloading without request-management.report', function () {
    $actor = reportActorWith([]);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'request-management-report']);
    Sanctum::actingAs($actor);

    $this->get("/api/request-management/report/{$run->id}/download")->assertForbidden();
});
