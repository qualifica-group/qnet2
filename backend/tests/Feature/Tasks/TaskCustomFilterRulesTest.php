<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Custom filter rules (spec 0158) on the REAL `tasks` domain: AC-001 (AND/OR
 * semantics against real + relation columns) and AC-003 (a custom filter
 * ignores filterModel/advancedFilters, and the export gives the SAME rows).
 */
if (! function_exists('taskActorWith')) {
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

function runTaskExportJob(ExportRun $run): void
{
    (new GenerateExportJob($run->id))->handle(app(ExportService::class));
}

afterEach(function () {
    Carbon::setTestNow();
});

it('AC-001: and [status in X, priority in Y] or [deadline today] returns (X and Y) or today', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 09:00:00'));

    $actor = taskActorWith(['viewAny', 'view']);
    $statusX = TaskStatus::factory()->create(['name' => 'Status X']);
    $statusOther = TaskStatus::factory()->create(['name' => 'Status Other']);
    $priorityY = TaskPriority::factory()->create(['name' => 'Priority Y']);
    $priorityOther = TaskPriority::factory()->create(['name' => 'Priority Other']);

    Task::factory()->forCreator($actor)->create([
        'title' => 'Matches-and', 'task_status_id' => $statusX->id, 'task_priority_id' => $priorityY->id,
        'end_date' => '2026-10-01',
    ]);
    Task::factory()->forCreator($actor)->create([
        'title' => 'Wrong-priority', 'task_status_id' => $statusX->id, 'task_priority_id' => $priorityOther->id,
        'end_date' => '2026-10-01',
    ]);
    Task::factory()->forCreator($actor)->create([
        'title' => 'Matches-or-today', 'task_status_id' => $statusOther->id, 'task_priority_id' => $priorityOther->id,
        'end_date' => '2026-09-25',
    ]);
    Task::factory()->forCreator($actor)->create([
        'title' => 'Matches-neither', 'task_status_id' => $statusOther->id, 'task_priority_id' => $priorityOther->id,
        'end_date' => '2026-10-02',
    ]);

    Sanctum::actingAs($actor);

    $items = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => [
            'and' => [
                ['field' => 'task_status', 'operator' => 'in', 'value' => ['Status X']],
                ['field' => 'task_priority', 'operator' => 'in', 'value' => ['Priority Y']],
            ],
            'or' => [
                ['field' => 'end_date', 'operator' => 'today'],
            ],
        ],
    ])->assertOk()->json('items');

    $titles = collect($items)->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(['Matches-and', 'Matches-or-today']);
});

it('AC-003: a custom filter ignores filterModel/advancedFilters, and the export gives the SAME rows', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $wanted = TaskStatus::factory()->create(['name' => 'Wanted status']);
    $other = TaskStatus::factory()->create(['name' => 'Other status']);

    Task::factory()->forCreator($actor)->create(['title' => 'Zeta', 'task_status_id' => $wanted->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Alfa', 'task_status_id' => $wanted->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Excluded', 'task_status_id' => $other->id]);

    Sanctum::actingAs($actor);

    $customFilterRules = ['and' => [['field' => 'task_status', 'operator' => 'in', 'value' => ['Wanted status']]]];
    // A contradicting filterModel/advancedFilters that, if actually applied,
    // would return a DIFFERENT (empty or smaller) set.
    $contradictingFilterModel = ['title' => ['filterType' => 'text', 'type' => 'equals', 'filter' => 'this-matches-nothing']];

    $rowsResponse = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => $customFilterRules,
        'filterModel' => $contradictingFilterModel,
        'advancedFilters' => ['assignment' => ['visible']],
    ])->assertOk();

    $rowTitles = collect($rowsResponse->json('items'))->pluck('title')->sort()->values()->all();
    expect($rowTitles)->toBe(['Alfa', 'Zeta']);

    Storage::fake('local');

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'tasks',
        'format' => ExportFormat::Csv,
        'state' => [
            'columns' => [['colId' => 'title', 'header' => 'Title']],
            'sortModel' => [['colId' => 'title', 'sort' => 'asc']],
            'customFilterRules' => $customFilterRules,
            'filterModel' => $contradictingFilterModel,
            'advancedFilters' => ['assignment' => ['visible']],
        ],
    ]);

    runTaskExportJob($run);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(ExportStatus::Completed);

    $csv = Storage::disk('local')->get($fresh->file_path);
    $lines = array_values(array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n"))));
    $exportedTitles = collect($lines)->skip(1)->map(fn (string $line): string => str_getcsv($line, ',', '"', '')[0])->sort()->values()->all();

    expect($exportedTitles)->toBe(['Alfa', 'Zeta']);
});
