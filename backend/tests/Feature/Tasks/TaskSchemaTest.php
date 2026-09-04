<?php

use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Schema of `tasks` and of the two user pivots (spec 0101, D-3/D-8/D-12)
|--------------------------------------------------------------------------
|
| Two absences are asserted as hard as the presences: `completion_percentage`
| (D-6 — the percentage is a projection of the status, a column here could
| drift from it) and every recurrence column (D-3 — recurrence is out of
| scope in BOTH phases, and anticipating it would be the exact mistake the
| decision forbids).
*/

// ---------------------------------------------------------------------------
// AC-003 — every declared column, and only those
// ---------------------------------------------------------------------------

it('AC-003: tasks carries every column of the data_contract', function () {
    $expected = [
        'id', 'title', 'description',
        'registry_id', 'referent_id', 'parent_task_id',
        'task_type_id', 'task_status_id', 'task_priority_id', 'task_importance_id', 'task_category_id',
        'opportunity_id', 'work_order_id', 'requester_id', 'creator_id',
        'start_date', 'end_date', 'completion_date', 'start_time', 'end_time', 'estimated_minutes',
        'is_blocked', 'requires_closure_feedback', 'closure_feedback',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('tasks', $column))->toBeTrue("tasks is missing column {$column}");
    }

    expect(Schema::getColumnListing('tasks'))->toEqualCanonicalizing($expected);
});

it('AC-003: tasks has NO completion_percentage column (D-6: the percentage is derived)', function () {
    expect(Schema::hasColumn('tasks', 'completion_percentage'))->toBeFalse();
});

it('AC-003: tasks has NO recurrence column of any kind (D-3)', function () {
    $suspicious = collect(Schema::getColumnListing('tasks'))
        ->filter(fn (string $column): bool => str_contains(strtolower($column), 'recurr'))
        ->all();

    expect($suspicious)->toBe([])
        ->and(Schema::hasTable('task_recurrences'))->toBeFalse();
});

it('AC-003: title and the two NOT NULL relations are enforced by the schema', function () {
    $status = TaskStatus::factory()->create();
    $creator = User::factory()->create();

    // task_status_id NOT NULL
    expect(fn () => DB::table('tasks')->insert([
        'title' => 'Senza stato', 'creator_id' => $creator->id, 'is_blocked' => false,
        'requires_closure_feedback' => false, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // creator_id NOT NULL (D-10)
    expect(fn () => DB::table('tasks')->insert([
        'title' => 'Senza creatore', 'task_status_id' => $status->id, 'is_blocked' => false,
        'requires_closure_feedback' => false, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// AC-003 — restrictOnDelete on parent_task_id and the six configurator FKs
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
dataset('taskConfiguratorForeignKeys', [
    'task_status_id' => ['task_status_id', 'task_statuses', TaskStatus::class],
    'task_type_id' => ['task_type_id', 'task_types', TaskType::class],
    'task_category_id' => ['task_category_id', 'task_categories', TaskCategory::class],
    'task_priority_id' => ['task_priority_id', 'task_priorities', TaskPriority::class],
    'task_importance_id' => ['task_importance_id', 'task_importances', TaskImportance::class],
]);

it('AC-003: each configurator FK is restrictOnDelete, so a referenced row cannot be dropped', function (string $column, string $table, string $modelClass) {
    $row = $modelClass::factory()->create();
    Task::factory()->create([$column => $row->id]);

    expect(fn () => DB::table($table)->where('id', $row->id)->delete())->toThrow(QueryException::class);
})->with('taskConfiguratorForeignKeys');

it('AC-003: parent_task_id is restrictOnDelete, so a parent with children cannot be dropped at DB level (D-8a)', function () {
    $parent = Task::factory()->create();
    Task::factory()->childOf($parent)->create();

    expect(fn () => DB::table('tasks')->where('id', $parent->id)->delete())->toThrow(QueryException::class);
});

it('AC-003: creator_id is restrictOnDelete: the author of a task cannot be dropped (D-10)', function () {
    $creator = User::factory()->create();
    Task::factory()->forCreator($creator)->create();

    expect(fn () => DB::table('users')->where('id', $creator->id)->delete())->toThrow(QueryException::class);
});

it('AC-003: the optional record links are nullOnDelete, they only clear the field', function () {
    $registry = Registry::factory()->create();
    $requester = User::factory()->create();
    $task = Task::factory()->create(['registry_id' => $registry->id, 'requester_id' => $requester->id]);

    DB::table('registries')->where('id', $registry->id)->delete();
    DB::table('users')->where('id', $requester->id)->delete();

    $fresh = $task->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->registry_id)->toBeNull()
        ->and($fresh->requester_id)->toBeNull();
});

it('AC-003: down() drops tasks, up() recreates it empty', function () {
    // The pivots reference `tasks`, so they come down first and back up
    // after: the migration under test is the middle one of the three.
    $assignee = require database_path('migrations/2026_09_04_100600_create_task_assignee_table.php');
    $watcher = require database_path('migrations/2026_09_04_100700_create_task_watcher_table.php');
    $tasks = require database_path('migrations/2026_09_04_100500_create_tasks_table.php');

    $assignee->down();
    $watcher->down();
    $tasks->down();
    expect(Schema::hasTable('tasks'))->toBeFalse();

    $tasks->up();
    $assignee->up();
    $watcher->up();
    expect(Schema::hasTable('tasks'))->toBeTrue()
        ->and(DB::table('tasks')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-004 — the two user pivots
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
dataset('taskUserPivots', [
    'task_assignee' => ['task_assignee', 'assignees', '2026_09_04_100600_create_task_assignee_table.php'],
    'task_watcher' => ['task_watcher', 'watchers', '2026_09_04_100700_create_task_watcher_table.php'],
]);

it('AC-004: the pivot has id/task_id/user_id and UNIQUE(task_id, user_id)', function (string $table) {
    foreach (['id', 'task_id', 'user_id'] as $column) {
        expect(Schema::hasColumn($table, $column))->toBeTrue("{$table} is missing column {$column}");
    }

    // Unordered sets (D-8): no `position` column, unlike work_order_participant.
    expect(Schema::getColumnListing($table))->toEqualCanonicalizing(['id', 'task_id', 'user_id']);

    $task = Task::factory()->create();
    $user = User::factory()->create();

    DB::table($table)->insert(['task_id' => $task->id, 'user_id' => $user->id]);

    expect(fn () => DB::table($table)->insert(['task_id' => $task->id, 'user_id' => $user->id]))
        ->toThrow(QueryException::class);
})->with('taskUserPivots');

it('AC-004: deleting the task cascades its pivot rows, the user survives', function (string $table, string $relation) {
    $task = Task::factory()->create();
    $user = User::factory()->create();
    $task->{$relation}()->attach($user->id);

    $task->delete();

    $this->assertDatabaseMissing($table, ['task_id' => $task->id]);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
})->with('taskUserPivots');

it('AC-004: deleting the user cascades its pivot rows, the task survives', function (string $table, string $relation) {
    $task = Task::factory()->create();
    $user = User::factory()->create();
    $task->{$relation}()->attach($user->id);

    DB::table('users')->where('id', $user->id)->delete();

    $this->assertDatabaseMissing($table, ['user_id' => $user->id]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
})->with('taskUserPivots');

it('AC-004: the same user may be both an assignee and a watcher of the same task (AC-083)', function () {
    $task = Task::factory()->create();
    $user = User::factory()->create();

    $task->assignees()->attach($user->id);
    $task->watchers()->attach($user->id);

    expect($task->assignees()->pluck('users.id')->all())->toBe([$user->id])
        ->and($task->watchers()->pluck('users.id')->all())->toBe([$user->id]);
});
