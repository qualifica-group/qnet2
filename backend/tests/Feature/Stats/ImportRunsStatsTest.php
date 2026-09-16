<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-009 — GET /api/stats/import-runs: every operator's leads runs
// ---------------------------------------------------------------------------

it('403 without import-runs.viewAny', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/stats/import-runs')->assertForbidden();
});

it('aggregates every operator\'s runs for the leads resource only', function () {
    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['viewAny']);

    ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Completed, 'imported_rows' => 5]);
    // Another user's leads run — counts too (runs are shared).
    ImportRun::factory()->create(['user_id' => User::factory()->create()->id, 'resource' => 'leads', 'status' => ImportStatus::Failed]);
    // The actor's own run, but a DIFFERENT resource — must NOT count.
    ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'companies', 'status' => ImportStatus::Completed]);

    Sanctum::actingAs($actor);

    $widgets = collect($this->getJson('/api/stats/import-runs')->assertOk()->json('data.widgets'))->keyBy('key');

    expect($widgets['total']['value'])->toBe(2)
        ->and($widgets['completed']['value'])->toBe(1)
        ->and($widgets['failed']['value'])->toBe(1)
        ->and($widgets['rows_imported']['value'])->toBe(5)
        ->and($widgets['by_status']['type'])->toBe('distribution')
        ->and(collect($widgets['by_status']['items'])->sum('value'))->toBe(2)
        ->and($widgets['trend']['type'])->toBe('trend')
        ->and($widgets['trend']['points'])->toHaveCount(12);
});
