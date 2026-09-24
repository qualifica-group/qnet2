<?php

use App\Enums\TaskStatusGroup;
use App\Models\ExportRun;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The export honours the advanced filters (spec 0032) exactly like the grid
|--------------------------------------------------------------------------
|
| The file must contain the rows the grid shows: the applied advanced
| filters are frozen into the run state, and a required filter omitted
| from the request falls back to its default, as on POST /rows.
*/

function exportTaskActor(): User
{
    foreach (['viewAny', 'export', 'viewAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['tasks.viewAny', 'tasks.export', 'tasks.viewAll']);

    return $user;
}

function exportedTaskCsv(array $payload): string
{
    $response = test()->postJson('/api/exports/tasks', [
        'format' => 'csv',
        'columns' => [['colId' => 'title', 'header' => 'Title']],
        ...$payload,
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    return Storage::disk('local')->get($run->file_path);
}

beforeEach(function () {
    Storage::fake('local');
    $actor = exportTaskActor();
    Sanctum::actingAs($actor);

    // Assigned to the actor, so the required `assignment` default
    // (assigned_to_me, spec 0153 D-1) keeps both and only `status` decides.
    Task::factory()->inStatus(TaskStatus::factory()->group(TaskStatusGroup::Open)->create())->create(['title' => 'Aperto'])
        ->assignees()->attach($actor->id);
    Task::factory()->inStatus(TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create())->create(['title' => 'Chiuso'])
        ->assignees()->attach($actor->id);
    Task::factory()->inStatus(TaskStatus::factory()->group(TaskStatusGroup::Open)->create())->create(['title' => 'Altrui']);
});

it('exports only the rows matching the applied advanced filters', function () {
    $csv = exportedTaskCsv(['advancedFilters' => ['status' => 'completed']]);

    expect($csv)->toContain('Chiuso')->not->toContain('Aperto');
});

it('applies the required advanced filter defaults when the request omits them, like the grid', function () {
    $csv = exportedTaskCsv([]);

    expect($csv)->toContain('Aperto')->not->toContain('Chiuso')->not->toContain('Altrui');
});

it('freezes the advanced filters into the run state', function () {
    exportedTaskCsv(['advancedFilters' => ['status' => 'all']]);

    expect(ExportRun::query()->latest('id')->firstOrFail()->state['advancedFilters'])->toBe(['status' => 'all']);
});

it('rejects an advanced filter outside the catalogue with 422', function () {
    $this->postJson('/api/exports/tasks', [
        'format' => 'csv',
        'columns' => [['colId' => 'title', 'header' => 'Title']],
        'advancedFilters' => ['not_a_filter' => 'x'],
    ])->assertUnprocessable()->assertJsonValidationErrors('advancedFilters.not_a_filter');
});
