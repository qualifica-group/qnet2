<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('importRunsExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function importRunsExportPayload(): array
    {
        return [
            'format' => 'csv',
            'columns' => [
                ['colId' => 'original_filename', 'header' => 'File'],
                ['colId' => 'status', 'header' => 'Status'],
            ],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-008 — POST /api/exports/import-runs
// ---------------------------------------------------------------------------

it('201 creates the ExportRun and dispatches GenerateExportJob with import-runs.export', function () {
    Queue::fake();
    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/import-runs', importRunsExportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'import-runs')
        ->assertJsonPath('data.export_run.format', 'csv');

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->user_id)->toBe($actor->id);

    Queue::assertPushed(GenerateExportJob::class);
});

it('403 without import-runs.export, no ExportRun created', function () {
    Queue::fake();
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/import-runs', importRunsExportPayload())->assertForbidden();

    expect(ExportRun::count())->toBe(0);
    Queue::assertNotPushed(GenerateExportJob::class);
});

it('exports the operator column as the operator name', function () {
    Storage::fake('local');
    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['export']);
    $operator = User::factory()->create(['name' => 'Mario Rossi']);
    ImportRun::factory()->create(['resource' => 'leads', 'user_id' => $operator->id, 'original_filename' => 'leads.csv']);

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'import-runs',
        'format' => ExportFormat::Csv,
        'state' => ['columns' => [
            ['colId' => 'user', 'header' => 'Operatore'],
            ['colId' => 'original_filename', 'header' => 'File'],
        ]],
    ]);

    (new GenerateExportJob($run->id))->handle(app(ExportService::class));

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(ExportStatus::Completed);

    $csv = trim(Storage::disk('local')->get($fresh->file_path), "\xEF\xBB\xBF\n");
    expect(explode("\n", $csv)[1])->toBe('"Mario Rossi",leads.csv');
});
