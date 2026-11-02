<?php

use App\Jobs\RunMassMigrationJob;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\Note;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MigrationPlanService;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
});

function emptyLegacyPage(): array
{
    return ['items' => [], 'pagination' => ['total' => 0]];
}

/**
 * @return list<Request>
 */
function legacyRequestsTo(string $path): array
{
    return Http::recorded()
        ->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => str_contains($request->url(), '/'.$path.'?') || str_ends_with($request->url(), '/'.$path))
        ->values()
        ->all();
}

// AC-001
it('sends updated_since with offset/limit on every request and stores it on the run', function () {
    Http::fake(['*' => Http::response(emptyLegacyPage())]);
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $response = $this->postJson('/api/migrations/notes/import', ['updated_since' => '2026-10-01'])
        ->assertCreated()
        ->assertJsonPath('data.migration_run.updated_since', '2026-10-01');

    $run = MigrationRun::query()->findOrFail($response->json('data.migration_run.id'));
    expect($run->updated_since?->format('Y-m-d'))->toBe('2026-10-01');

    runMigrationJobFor($run);

    $requests = legacyRequestsTo('notes');
    expect($requests)->not->toBeEmpty();
    foreach ($requests as $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        expect($query)->toMatchArray(['updated_since' => '2026-10-01', 'offset' => '0'])->toHaveKey('limit');
    }
});

// AC-002
it('imports in full when no day is given', function (?array $body) {
    Http::fake(['*' => Http::response(emptyLegacyPage())]);
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $response = $this->postJson('/api/migrations/notes/import', $body ?? [])
        ->assertCreated()
        ->assertJsonPath('data.migration_run.updated_since', null);

    runMigrationJobFor(MigrationRun::query()->findOrFail($response->json('data.migration_run.id')));

    foreach (legacyRequestsTo('notes') as $request) {
        expect($request->url())->not->toContain('updated_since');
    }
})->with([[null], [['updated_since' => null]]]);

// AC-003 (REQUIREMENT CHANGED 0345 D-8: every source accepts the day)
it('accepts a day on any source and sends it to the legacy', function () {
    Http::fake(['*' => Http::response(emptyLegacyPage())]);
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $response = $this->postJson('/api/migrations/roles/import', ['updated_since' => '2026-10-01'])
        ->assertCreated()
        ->assertJsonPath('data.migration_run.updated_since', '2026-10-01');

    runMigrationJobFor(MigrationRun::query()->findOrFail($response->json('data.migration_run.id')));

    $requests = legacyRequestsTo('roles');
    expect($requests)->not->toBeEmpty();
    foreach ($requests as $request) {
        expect($request->url())->toContain('updated_since=2026-10-01');
    }
});

// AC-004
it('rejects an invalid or future day on single and mass import', function (string $value) {
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->postJson('/api/migrations/notes/import', ['updated_since' => $value])
        ->assertStatus(422)->assertJsonValidationErrors('updated_since');
    $this->postJson('/api/migrations/mass-runs', ['updated_since' => $value])
        ->assertStatus(422)->assertJsonValidationErrors('updated_since');
})->with(['bad format' => ['01/10/2026'], 'future' => [fn () => now()->addDay()->format('Y-m-d')]]);

// AC-005 (REQUIREMENT CHANGED 0345 D-8: every child inherits and sends the day)
it('mass import: every child inherits the day and sends it', function () {
    Http::fake(['*' => Http::response(emptyLegacyPage())]);
    Queue::fake();
    $actor = migrationsSuperAdminActor();
    Sanctum::actingAs($actor);

    app(MigrationPlanService::class)->save([
        ['source' => 'notes', 'enabled' => true],
        ['source' => 'roles', 'enabled' => true],
    ]);

    $response = $this->postJson('/api/migrations/mass-runs', ['updated_since' => '2026-10-01'])
        ->assertCreated()
        ->assertJsonPath('data.mass_migration_run.updated_since', '2026-10-01');

    $massRun = MassMigrationRun::query()->findOrFail($response->json('data.mass_migration_run.id'));
    expect($massRun->updated_since?->format('Y-m-d'))->toBe('2026-10-01');

    $massRun->forceFill(['sources' => ['notes', 'roles']])->save();
    (new RunMassMigrationJob($massRun->id))->handle(app(MigrationService::class));

    $children = $massRun->runs()->orderBy('id')->get()->keyBy('source');
    expect($children['notes']->updated_since?->format('Y-m-d'))->toBe('2026-10-01')
        ->and($children['roles']->updated_since?->format('Y-m-d'))->toBe('2026-10-01');

    expect(legacyRequestsTo('notes')[0]->url())->toContain('updated_since=2026-10-01');
    expect(legacyRequestsTo('roles')[0]->url())->toContain('updated_since=2026-10-01');
});

// AC-006
it('preview never sends updated_since', function () {
    Http::fake(['*' => Http::response(emptyLegacyPage())]);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/notes/preview')->assertOk();

    expect(legacyRequestsTo('notes'))->not->toBeEmpty();
    foreach (legacyRequestsTo('notes') as $request) {
        expect($request->url())->not->toContain('updated_since');
    }
});

// AC-007 (REQUIREMENT CHANGED 0345 D-8: no per-source incremental flag)
it('does not expose an incremental flag on the sources', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $sources = collect($this->getJson('/api/migrations')->assertOk()->json('data.sources'));

    expect($sources)->not->toBeEmpty()
        ->and($sources->contains(fn (array $source): bool => array_key_exists('incremental', $source)))->toBeFalse();
});

// AC-008
it('exposes updated_since on single and mass run polling', function () {
    $actor = migrationsSuperAdminActor();
    Sanctum::actingAs($actor);

    $massRun = MassMigrationRun::factory()->create(['user_id' => $actor->id, 'sources' => ['notes'], 'updated_since' => '2026-10-01']);
    $child = MigrationRun::factory()->create([
        'user_id' => $actor->id, 'source' => 'notes', 'mass_migration_run_id' => $massRun->id, 'updated_since' => '2026-10-01',
    ]);
    $plain = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'notes']);

    $this->getJson("/api/migrations/mass-runs/{$massRun->id}")
        ->assertOk()
        ->assertJsonPath('data.mass_migration_run.updated_since', '2026-10-01')
        ->assertJsonPath('data.mass_migration_run.runs.0.updated_since', '2026-10-01');
    $this->getJson("/api/migrations/notes/runs/{$child->id}")
        ->assertJsonPath('data.migration_run.updated_since', '2026-10-01');
    $this->getJson("/api/migrations/notes/runs/{$plain->id}")
        ->assertJsonPath('data.migration_run.updated_since', null);
});

// AC-009
it('forbids a non super-admin even with a day', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/migrations/notes/import', ['updated_since' => '2026-10-01'])->assertForbidden();
    $this->postJson('/api/migrations/mass-runs', ['updated_since' => '2026-10-01'])->assertForbidden();
});

// AC-010
it('incremental import skips already migrated rows and creates new ones', function () {
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['old_id' => 19123])->save();
    $author = User::factory()->create(['old_id' => 79]);

    $record = fn (int $id): array => [
        'id' => $id, 'subject_type' => 'work_order', 'subject_id' => 19123, 'parent_id' => null,
        'kind' => 'note', 'body' => '<p>Ciao</p>', 'author_user_id' => 79, 'warnings' => [],
        'edited_at' => null, 'created_at' => '2026-10-21 19:53:05', 'updated_at' => '2026-10-21 19:53:05',
    ];
    Http::fake(['*' => Http::response(['items' => [$record(501), $record(502)], 'pagination' => ['total' => 2]])]);

    $first = MigrationRun::factory()->create(['user_id' => $author->id, 'source' => 'notes', 'updated_since' => '2026-10-01']);
    runMigrationJobFor($first);
    expect($first->fresh()->created_rows)->toBe(2);

    $second = MigrationRun::factory()->create(['user_id' => $author->id, 'source' => 'notes', 'updated_since' => '2026-10-01']);
    runMigrationJobFor($second);
    expect($second->fresh()->skipped_rows)->toBe(2)->and(Note::query()->count())->toBe(2);
});
