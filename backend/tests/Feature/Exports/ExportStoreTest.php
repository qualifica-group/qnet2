<?php

use App\Http\Requests\Table\TableRowsRequest;
use App\Jobs\GenerateExportJob;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubExportTableDefinition;

uses(RefreshDatabase::class);

if (! function_exists('stubExportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function stubExportActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('registerStubExportDomain')) {
    function registerStubExportDomain(): void
    {
        config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    }
}

if (! function_exists('exportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function exportPayload(array $overrides = []): array
    {
        return array_merge([
            'format' => 'csv',
            'columns' => [
                ['colId' => 'name', 'header' => 'Name'],
            ],
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001 — POST /api/exports/{domain}
// ---------------------------------------------------------------------------

it('201 + creates the ExportRun(status=processing) + dispatches GenerateExportJob', function () {
    registerStubExportDomain();
    Queue::fake();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/stub-exports', exportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'stub-exports')
        ->assertJsonPath('data.export_run.format', 'csv')
        ->assertJsonPath('data.export_run.row_count', null)
        ->assertJsonPath('data.export_run.has_file', false);

    $runId = $response->json('data.export_run.id');
    $run = ExportRun::findOrFail($runId);

    expect($run->user_id)->toBe($actor->id)
        ->and($run->original_filename)->toBe('stub-exports-'.now()->format('Y-m-d').'.csv')
        ->and($run->state['columns'])->toBe([['colId' => 'name', 'header' => 'Name']]);

    Queue::assertPushed(GenerateExportJob::class);
});

it('201 accepts xlsx as the format', function () {
    registerStubExportDomain();
    Queue::fake();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload(['format' => 'xlsx']))
        ->assertCreated()
        ->assertJsonPath('data.export_run.format', 'xlsx');
});

// ---------------------------------------------------------------------------
// AC-002 — 403 without {domain}.export ability / 404 unknown domain
// ---------------------------------------------------------------------------

it('403 without business-functions.export', function () {
    registerStubExportDomain();
    Queue::fake();
    $actor = stubExportActorWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload())->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

it('404 for an unregistered domain', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/unknown-domain', exportPayload())->assertNotFound();
});

it('requires authentication', function () {
    registerStubExportDomain();

    $this->postJson('/api/exports/stub-exports', exportPayload())->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// AC-004 — validation matrix (422)
// ---------------------------------------------------------------------------

it('422 when format is not in the allow-list', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload(['format' => 'pdf']))
        ->assertStatus(422)->assertJsonValidationErrors('format');
});

it('422 when columns is empty', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload(['columns' => []]))
        ->assertStatus(422)->assertJsonValidationErrors('columns');
});

it('422 when a column colId is not in the definition allow-list', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload([
        'columns' => [['colId' => 'not-a-real-column', 'header' => 'X']],
    ]))->assertStatus(422)->assertJsonValidationErrors('columns.0.colId');
});

it('422 when sortModel colId is not sortable', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload([
        'sortModel' => [['colId' => 'tags', 'sort' => 'asc']],
    ]))->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');
});

it('422 when filterModel targets a non-filterable column', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload([
        'filterModel' => ['tags' => ['filter' => 'x', 'type' => 'contains']],
    ]))->assertStatus(422)->assertJsonValidationErrors('filterModel.tags');
});

it('422 when search exceeds the rows endpoint cap', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/stub-exports', exportPayload([
        'search' => str_repeat('a', TableRowsRequest::SEARCH_MAX_LENGTH + 1),
    ]))->assertStatus(422)->assertJsonValidationErrors('search');
});

it('never creates a run when validation fails', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);
    BusinessFunction::factory()->create();

    $this->postJson('/api/exports/stub-exports', exportPayload(['format' => 'pdf']))
        ->assertStatus(422);

    expect(ExportRun::query()->count())->toBe(0);
});
