<?php

use App\Jobs\RunMassMigrationJob;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\User;
use App\Services\MigrationPlanService;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Stubs\StubUpdatableMigrationSource;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    config(['migrations.definitions' => [
        ...config('migrations.definitions'),
        'stub-updatable' => StubUpdatableMigrationSource::class,
    ]]);
});

// AC-001 (common part): without the flag nothing is updated and counters stay as today
it('counts existing rows as skipped when the run does not ask to update them', function () {
    Http::fake(['*' => Http::response([
        'items' => [['id' => 1, 'action' => 'create'], ['id' => 2, 'action' => 'existing'], ['id' => 3, 'action' => 'skip']],
        'pagination' => ['total' => 3],
    ])]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'stub-updatable']);
    runMigrationJobFor($run);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(2)
        ->and($fresh->updated_rows)->toBe(0)
        ->and($fresh->total_rows)->toBe(3);
});

it('counts an updated row apart, with its warnings in the report', function () {
    Http::fake(['*' => Http::response([
        'items' => [['id' => 1, 'action' => 'create'], ['id' => 2, 'action' => 'existing'], ['id' => 3, 'action' => 'skip']],
        'pagination' => ['total' => 3],
    ])]);

    $run = MigrationRun::factory()->create([
        'user_id' => migrationsSuperAdminActor()->id, 'source' => 'stub-updatable', 'update_existing' => true,
    ]);
    runMigrationJobFor($run);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(1)
        ->and($fresh->updated_rows)->toBe(1)
        ->and($fresh->total_rows)->toBe(3)
        ->and(collect($fresh->report)->pluck('message')->all())->toBe(['Realigned.', 'Left as is.']);
});

it('ignores the flag on a source that does not support it', function () {
    Http::fake(['*' => Http::response(['items' => [], 'pagination' => ['total' => 0]])]);

    $run = MigrationRun::factory()->create([
        'user_id' => migrationsSuperAdminActor()->id, 'source' => 'roles', 'update_existing' => true,
    ]);
    runMigrationJobFor($run);

    expect($run->fresh()->updated_rows)->toBe(0);
});

// AC-020
it('starts a single import with update_existing and rejects it on an unsupported source', function () {
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->postJson('/api/migrations/stub-updatable/import', ['update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.migration_run.update_existing', true)
        ->assertJsonPath('data.migration_run.updated_rows', 0);

    $this->postJson('/api/migrations/stub-updatable/import')
        ->assertCreated()
        ->assertJsonPath('data.migration_run.update_existing', false);

    $this->postJson('/api/migrations/roles/import', ['update_existing' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors('update_existing')
        ->assertJsonPath('success', false);

    $this->postJson('/api/migrations/stub-updatable/import', ['update_existing' => 'maybe'])
        ->assertStatus(422)->assertJsonValidationErrors('update_existing');

    $this->postJson('/api/migrations/roles/import', ['update_existing' => false])->assertCreated();
    expect(MigrationRun::query()->where('source', 'roles')->count())->toBe(1);
});

it('forbids a non super-admin from starting an update import', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/migrations/stub-updatable/import', ['update_existing' => true])->assertForbidden();
    $this->postJson('/api/migrations/mass-runs', ['update_existing' => true])->assertForbidden();
});

// AC-021
it('mass import saves the flag and only the supporting children inherit it', function () {
    Http::fake(['*' => Http::response(['items' => [], 'pagination' => ['total' => 0]])]);
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    app(MigrationPlanService::class)->save([
        ['source' => 'roles', 'enabled' => true],
        ['source' => 'stub-updatable', 'enabled' => true],
    ]);

    $response = $this->postJson('/api/migrations/mass-runs', ['update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.mass_migration_run.update_existing', true);

    $massRun = MassMigrationRun::query()->findOrFail($response->json('data.mass_migration_run.id'));
    expect($massRun->update_existing)->toBeTrue();

    $massRun->forceFill(['sources' => ['roles', 'stub-updatable']])->save();
    (new RunMassMigrationJob($massRun->id))->handle(app(MigrationService::class));

    $children = $massRun->runs()->orderBy('id')->get()->keyBy('source');
    expect($children['roles']->update_existing)->toBeFalse()
        ->and($children['stub-updatable']->update_existing)->toBeTrue();
});

it('mass import without the flag leaves every child off', function () {
    Http::fake(['*' => Http::response(['items' => [], 'pagination' => ['total' => 0]])]);
    $massRun = MassMigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'sources' => ['stub-updatable']]);

    (new RunMassMigrationJob($massRun->id))->handle(app(MigrationService::class));

    expect($massRun->runs()->first()->update_existing)->toBeFalse();
});

// AC-022
it('exposes update_existing, updated_rows and supports_update_existing', function () {
    $actor = migrationsSuperAdminActor();
    Sanctum::actingAs($actor);

    $massRun = MassMigrationRun::factory()->create(['user_id' => $actor->id, 'sources' => ['stub-updatable'], 'update_existing' => true]);
    $child = MigrationRun::factory()->create([
        'user_id' => $actor->id, 'source' => 'stub-updatable', 'mass_migration_run_id' => $massRun->id,
        'update_existing' => true, 'updated_rows' => 4,
    ]);

    $this->getJson("/api/migrations/mass-runs/{$massRun->id}")
        ->assertOk()
        ->assertJsonPath('data.mass_migration_run.update_existing', true)
        ->assertJsonPath('data.mass_migration_run.runs.0.update_existing', true)
        ->assertJsonPath('data.mass_migration_run.runs.0.updated_rows', 4);
    $this->getJson("/api/migrations/stub-updatable/runs/{$child->id}")
        ->assertJsonPath('data.migration_run.update_existing', true)
        ->assertJsonPath('data.migration_run.updated_rows', 4);

    $sources = collect($this->getJson('/api/migrations')->assertOk()->json('data.sources'))->keyBy('key');
    expect($sources['stub-updatable']['supports_update_existing'])->toBeTrue()
        ->and($sources['roles']['supports_update_existing'])->toBeFalse();
});
