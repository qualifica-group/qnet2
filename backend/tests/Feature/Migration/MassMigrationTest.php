<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMassMigrationJob;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationPlanService;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMassMigrationJobFor')) {
    function runMassMigrationJobFor(MassMigrationRun $run): void
    {
        (new RunMassMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

if (! function_exists('emptyExternalPage')) {
    function emptyExternalPage(): array
    {
        return ['items' => [], 'pagination' => ['total' => 0]];
    }
}

// ---------------------------------------------------------------------------
// AC-006 — happy path: children created in order, linked, parent completed
// ---------------------------------------------------------------------------

it('runs every source in order, links each child run, and completes the parent', function () {
    seedMigrationsConfig();
    Http::fake(['*' => Http::response(emptyExternalPage())]);

    $actor = migrationsSuperAdminActor();
    $massRun = MassMigrationRun::factory()->create([
        'user_id' => $actor->id,
        'sources' => ['companies', 'users'],
    ]);

    runMassMigrationJobFor($massRun);

    $children = $massRun->runs()->orderBy('id')->get();

    expect($massRun->fresh()->status)->toBe(MigrationStatus::Completed)
        ->and($children->pluck('source')->all())->toBe(['companies', 'users'])
        ->and($children->every(fn (MigrationRun $r): bool => $r->status === MigrationStatus::Completed))->toBeTrue()
        ->and($children->every(fn (MigrationRun $r): bool => $r->mass_migration_run_id === $massRun->id))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-007 — stop-on-failure: chain halts, later sources never run
// ---------------------------------------------------------------------------

it('stops the chain at the first failing source and marks the parent failed', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response(['error' => 'boom'], 500),
        '*' => Http::response(emptyExternalPage()),
    ]);

    $actor = migrationsSuperAdminActor();
    $massRun = MassMigrationRun::factory()->create([
        'user_id' => $actor->id,
        'sources' => ['companies', 'users', 'roles'],
    ]);

    runMassMigrationJobFor($massRun);

    $children = $massRun->runs()->orderBy('id')->get();

    expect($massRun->fresh()->status)->toBe(MigrationStatus::Failed)
        ->and($children->pluck('source')->all())->toBe(['companies', 'users'])
        ->and($children->firstWhere('source', 'companies')->status)->toBe(MigrationStatus::Completed)
        ->and($children->firstWhere('source', 'users')->status)->toBe(MigrationStatus::Failed)
        // 'roles' was never reached: no child row for it.
        ->and($children->firstWhere('source', 'roles'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-008 / AC-011 — POST /api/migrations/mass-runs
// ---------------------------------------------------------------------------

it('store: 201 snapshots only the enabled sources in order and dispatches the orchestrator', function () {
    Queue::fake();
    $actor = migrationsSuperAdminActor();

    app(MigrationPlanService::class)->save([
        ['source' => 'companies', 'enabled' => true],
        ['source' => 'users', 'enabled' => false],
        ['source' => 'roles', 'enabled' => true],
    ]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/migrations/mass-runs')
        ->assertStatus(201)
        ->assertJsonPath('data.mass_migration_run.status', 'pending')
        ->assertJsonPath('data.mass_migration_run.runs', []);

    // 'users' disabled -> excluded; enabled keep their relative order; the rest
    // of the registered sources are reconciled in as enabled and appended.
    $sources = $response->json('data.mass_migration_run.sources');
    expect(array_slice($sources, 0, 2))->toBe(['companies', 'roles'])
        ->and($sources)->not->toContain('users');

    Queue::assertPushed(RunMassMigrationJob::class);
});

it('store: 422 when the plan has no enabled sources', function () {
    Queue::fake();
    $actor = migrationsSuperAdminActor();

    app(MigrationPlanService::class)->save(array_map(
        static fn (string $key): array => ['source' => $key, 'enabled' => false],
        array_keys(config('migrations.definitions')),
    ));

    Sanctum::actingAs($actor);

    $this->postJson('/api/migrations/mass-runs')->assertStatus(422);
    Queue::assertNothingPushed();
});

it('store: 403 for a non-super-admin', function () {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/migrations/mass-runs')->assertForbidden();
    Queue::assertNothingPushed();
});

// ---------------------------------------------------------------------------
// AC-012 — GET /api/migrations/mass-runs/{massMigrationRun}
// ---------------------------------------------------------------------------

it('show: 200 with status, planned sources and ordered children with report', function () {
    $actor = migrationsSuperAdminActor();
    $massRun = MassMigrationRun::factory()->create([
        'user_id' => $actor->id,
        'sources' => ['companies', 'users'],
        'status' => MigrationStatus::Processing,
    ]);
    MigrationRun::factory()->create([
        'user_id' => $actor->id,
        'mass_migration_run_id' => $massRun->id,
        'source' => 'companies',
        'status' => MigrationStatus::Completed,
        'report' => [['old_id' => 3, 'level' => 'warning', 'message' => 'Unresolved reference.']],
    ]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/mass-runs/{$massRun->id}")
        ->assertOk()
        ->assertJsonPath('data.mass_migration_run.status', 'processing')
        ->assertJsonPath('data.mass_migration_run.sources', ['companies', 'users'])
        ->assertJsonPath('data.mass_migration_run.runs.0.source', 'companies')
        ->assertJsonPath('data.mass_migration_run.runs.0.report.0.message', 'Unresolved reference.');
});

it('show: 404 for a mass run belonging to another user', function () {
    $actor = migrationsSuperAdminActor();
    $other = User::factory()->create();
    $massRun = MassMigrationRun::factory()->create(['user_id' => $other->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/mass-runs/{$massRun->id}")->assertNotFound();
});

it('show: 403 for a non-super-admin', function () {
    $actor = User::factory()->create();
    $massRun = MassMigrationRun::factory()->create(['user_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/mass-runs/{$massRun->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// A worker that kills the job never leaves the runs `processing`
// ---------------------------------------------------------------------------

it('fails the aggregate run and the in-flight child when the worker kills the job', function () {
    $actor = migrationsSuperAdminActor();
    $massRun = MassMigrationRun::factory()->create([
        'user_id' => $actor->id,
        'sources' => ['companies', 'users'],
        'status' => MigrationStatus::Processing,
    ]);
    $done = MigrationRun::factory()->create([
        'user_id' => $actor->id,
        'source' => 'companies',
        'mass_migration_run_id' => $massRun->id,
        'status' => MigrationStatus::Completed,
    ]);
    $inFlight = MigrationRun::factory()->create([
        'user_id' => $actor->id,
        'source' => 'users',
        'mass_migration_run_id' => $massRun->id,
        'status' => MigrationStatus::Processing,
    ]);

    (new RunMassMigrationJob($massRun->id))->failed(new RuntimeException('killed'));

    expect($massRun->fresh()->status)->toBe(MigrationStatus::Failed)
        ->and($inFlight->fresh()->status)->toBe(MigrationStatus::Failed)
        ->and($done->fresh()->status)->toBe(MigrationStatus::Completed);
});

it('leaves a completed aggregate run untouched when a late failure lands', function () {
    $actor = migrationsSuperAdminActor();
    $massRun = MassMigrationRun::factory()->create([
        'user_id' => $actor->id,
        'status' => MigrationStatus::Completed,
    ]);

    (new RunMassMigrationJob($massRun->id))->failed(new RuntimeException('killed'));

    expect($massRun->fresh()->status)->toBe(MigrationStatus::Completed);
});

it('exposes tries = 1 and timeout = config migrations.job_timeout', function () {
    config(['migrations.job_timeout' => 4321]);

    $job = new RunMassMigrationJob(1);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(4321);
});

it('is pushed on the dedicated migrations queue, never on default', function () {
    config(['migrations.queue' => 'migrations-test']);

    $job = new RunMassMigrationJob(1);

    expect($job->queue)->toBe('migrations-test');
});
