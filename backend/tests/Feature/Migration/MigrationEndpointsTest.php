<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-004 / AC-015 — GET /api/migrations
// ---------------------------------------------------------------------------

it('index: 200 with the registered sources for a super-admin', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sources.0.key', 'roles')
        ->assertJsonPath('data.sources.1.key', 'users');
});

it('index: 403 for an authenticated non-super-admin', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/migrations')->assertForbidden();
});

it('index: 401 for an anonymous request', function () {
    $this->getJson('/api/migrations')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// AC-016 — GET /api/migrations/{source}/columns
// ---------------------------------------------------------------------------

it('columns: 200 with the column catalogue', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/roles/columns')
        ->assertOk()
        ->assertJsonPath('data.columns.1.id', 'name');
});

it('columns: 404 for an unknown source', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/unknown-source/columns')->assertNotFound();
});

it('columns: 200 with the expected request template and a copyable sample, without calling the external system', function () {
    seedMigrationsConfig();
    config(['migrations.token' => 'super-secret-token']);
    Http::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $response = $this->getJson('/api/migrations/roles/columns')
        ->assertOk()
        ->assertJsonPath('data.request.method', 'GET')
        ->assertJsonPath('data.request.base_url', fakeMigrationsBaseUrl())
        ->assertJsonPath('data.request.path', 'roles')
        ->assertJsonPath('data.request.url', fakeMigrationsBaseUrl().'/roles')
        ->assertJsonPath('data.sample.items.0.id', 1)
        ->assertJsonPath('data.sample.items.0.name', 'Name')
        ->assertJsonPath('data.sample.pagination.total', 1)
        ->assertJsonPath('data.sample.pagination.offset', 0)
        ->assertJsonPath('data.sample.pagination.limit', 50)
        ->assertJsonPath('data.sample.pagination.total_pages', 1);

    expect($response->json('data.request'))->not->toHaveKey('token')
        ->and(json_encode($response->json('data.request')))->not->toContain('super-secret-token');

    Http::assertNothingSent();
});

it('columns: the users sample template includes is_active and the roles relation', function () {
    seedMigrationsConfig();
    Http::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/users/columns')
        ->assertOk()
        ->assertJsonPath('data.sample.items.0.is_active', true)
        ->assertJsonPath('data.sample.items.0.roles.0.id', 1)
        ->assertJsonPath('data.sample.items.0.roles.0.name', 'Role name');

    Http::assertNothingSent();
});

it('columns: base_url empty falls back to the bare path', function () {
    config(['migrations.base_url' => '']);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/roles/columns')
        ->assertOk()
        ->assertJsonPath('data.request.base_url', '')
        ->assertJsonPath('data.request.url', 'roles');
});

// ---------------------------------------------------------------------------
// AC-016 — GET /api/migrations/{source}/preview
// ---------------------------------------------------------------------------

it('preview: 200 with normalized rows and pagination', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 1, 'name' => 'operator']],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 10, 'total_pages' => 1],
        ]),
    ]);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/roles/preview?page=1&per_page=10')
        ->assertOk()
        ->assertJsonPath('data.rows.0.name', 'operator')
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.pagination.has_more', false);
});

it('preview: 422 when per_page exceeds the configured max', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/roles/preview?per_page=99999')->assertStatus(422);
});

it('preview: 404 for an unknown source', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/unknown-source/preview')->assertNotFound();
});

it('preview: 502 when the external system errors', function () {
    seedMigrationsConfig();
    Http::fake([fakeMigrationsBaseUrl().'/roles*' => Http::response(['error' => 'boom'], 500)]);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/roles/preview')->assertStatus(502);
});

it('preview: 504 when the external system times out', function () {
    seedMigrationsConfig();
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/roles/preview')->assertStatus(504);
});

it('preview: 403 for a non-super-admin', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/migrations/roles/preview')->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-017 — POST /api/migrations/{source}/import
// ---------------------------------------------------------------------------

it('import: 201 with a pending run and dispatches RunMigrationJob', function () {
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->postJson('/api/migrations/roles/import')
        ->assertStatus(201)
        ->assertJsonPath('data.migration_run.source', 'roles')
        ->assertJsonPath('data.migration_run.status', 'pending')
        ->assertJsonPath('data.migration_run.has_report', false);

    Queue::assertPushed(RunMigrationJob::class);
});

it('import: 404 for an unknown source', function () {
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->postJson('/api/migrations/unknown-source/import')->assertNotFound();
    Queue::assertNothingPushed();
});

it('import: 403 for a non-super-admin', function () {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/migrations/roles/import')->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-018 — GET /api/migrations/{source}/runs/{migrationRun}
// ---------------------------------------------------------------------------

it('run: 200 with status, counters and report', function () {
    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create([
        'user_id' => $actor->id,
        'source' => 'roles',
        'status' => MigrationStatus::Completed,
        'report' => [['old_id' => 2, 'level' => 'warning', 'message' => 'Unresolved role reference.']],
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/roles/runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.migration_run.status', 'completed')
        ->assertJsonPath('data.migration_run.report.0.message', 'Unresolved role reference.');
});

it('run: 404 for a run belonging to another user (ownership)', function () {
    $actor = migrationsSuperAdminActor();
    $otherUser = User::factory()->create();
    $run = MigrationRun::factory()->create(['user_id' => $otherUser->id, 'source' => 'roles']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/roles/runs/{$run->id}")->assertNotFound();
});

it('run: 404 when the source does not match the route', function () {
    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/roles/runs/{$run->id}")->assertNotFound();
});

it('run: 403 for a non-super-admin', function () {
    $actor = User::factory()->create();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/migrations/roles/runs/{$run->id}")->assertForbidden();
});
