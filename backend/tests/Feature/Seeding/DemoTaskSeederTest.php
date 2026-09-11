<?php

use App\Models\Note;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Database\Seeders\DemoTaskNoteSeeder;
use Database\Seeders\DemoTaskSeeder;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Everything a demo Task may point at: the classification vocabulary (the
 * client's own reference data, which DemoDataSeeder runs right before the demo
 * step) plus the users and the anagrafiche/referenti the links resolve from.
 */
function seedTaskDependencies(): void
{
    test()->seed(RolePermissionSeeder::class);
    test()->seed(QualificaTaskTaxonomySeeder::class);

    User::factory()->count(8)->create();

    Registry::factory()->count(3)->create()->each(
        fn (Registry $registry) => Referent::factory()->count(2)->create()
            ->each(fn (Referent $referent) => $referent->registries()->attach($registry))
    );
}

it('seeds the tasks hierarchy with both membership pivots', function (): void {
    seedTaskDependencies();

    test()->seed(DemoTaskSeeder::class);

    $tasks = Task::query()->with(['assignees', 'watchers'])->get();

    expect($tasks)->toHaveCount(60)
        ->and($tasks->whereNotNull('parent_task_id'))->toHaveCount(20)
        ->and($tasks->every(fn (Task $task): bool => $task->creator_id !== null))->toBeTrue()
        ->and($tasks->every(fn (Task $task): bool => $task->assignees->isNotEmpty()))->toBeTrue()
        ->and($tasks->contains(fn (Task $task): bool => $task->watchers->isNotEmpty()))->toBeTrue()
        ->and($tasks->contains(fn (Task $task): bool => $task->is_blocked))->toBeTrue();
});

it('classifies the tasks on the seeded vocabulary, never on an invented one', function (): void {
    seedTaskDependencies();

    test()->seed(DemoTaskSeeder::class);

    $tasks = Task::query()->get();

    expect(TaskType::count())->toBeGreaterThan(0)
        ->and($tasks->whereNotNull('task_type_id'))->not->toBeEmpty()
        ->and($tasks->whereNotNull('task_category_id'))->not->toBeEmpty()
        ->and($tasks->whereNotNull('task_priority_id'))->not->toBeEmpty()
        ->and($tasks->whereNotNull('task_importance_id'))->not->toBeEmpty();

    foreach ($tasks as $task) {
        expect($task->task_status_id)->toBeIn(TaskStatus::query()->pluck('id')->all())
            ->and($task->task_type_id)->toBeIn([null, ...TaskType::query()->pluck('id')->all()])
            ->and($task->task_category_id)->toBeIn([null, ...TaskCategory::query()->pluck('id')->all()])
            ->and($task->task_priority_id)->toBeIn([null, ...TaskPriority::query()->pluck('id')->all()])
            ->and($task->task_importance_id)->toBeIn([null, ...TaskImportance::query()->pluck('id')->all()]);
    }
});

it('goes through the real write path: referente coherence and closing dates hold', function (): void {
    seedTaskDependencies();

    test()->seed(DemoTaskSeeder::class);

    $tasks = Task::query()->with('taskStatus')->get();

    expect($tasks->whereNotNull('referent_id'))->not->toBeEmpty();

    foreach ($tasks as $task) {
        if ($task->referent_id !== null) {
            // AC-014: the referente belongs to the Task's own anagrafica.
            expect(DB::table('referent_registry')
                ->where('referent_id', $task->referent_id)
                ->where('registry_id', $task->registry_id)
                ->exists())->toBeTrue($task->title);
        }

        // D-7: a Task in a closing phase carries its completion date; an open
        // one carries none. Finished work is never dated in the future.
        expect($task->completion_date !== null)->toBe($task->taskStatus->isClosing(), $task->title);

        if ($task->completion_date !== null) {
            expect($task->completion_date->startOfDay()->lessThanOrEqualTo(now()->startOfDay()))
                ->toBeTrue($task->title)
                ->and($task->completion_date->startOfDay()->greaterThanOrEqualTo($task->start_date->startOfDay()))
                ->toBeTrue($task->title);
        }

        if ($task->taskStatus->isClosing() && $task->requires_closure_feedback) {
            expect(trim((string) $task->closure_feedback))->not->toBe('');
        }
    }
});

it('is idempotent: a second run replaces the dataset instead of piling onto it', function (): void {
    seedTaskDependencies();

    test()->seed(DemoTaskSeeder::class);
    test()->seed(DemoTaskSeeder::class);

    expect(Task::count())->toBe(60)
        ->and(DB::table('task_assignee')->count())->toBeGreaterThan(0)
        ->and(DB::table('task_watcher')->count())->toBeGreaterThan(0);
});

it('seeds task threads written only by members who may read the task', function (): void {
    seedTaskDependencies();
    User::query()->get()->each(fn (User $user) => $user->givePermissionTo('tasks.view'));

    test()->seed(DemoTaskSeeder::class);
    test()->seed(DemoTaskNoteSeeder::class);

    $notes = Note::query()->where('notable_type', (new Task)->getMorphClass())->get();

    expect($notes)->not->toBeEmpty()
        ->and($notes->whereNotNull('parent_id'))->not->toBeEmpty()
        // D-6: a Task has no scoping unit, so no note on it carries one.
        ->and($notes->whereNotNull('quote_id'))->toBeEmpty();

    foreach ($notes->groupBy('notable_id') as $taskId => $thread) {
        $task = Task::query()->with(['assignees', 'watchers'])->findOrFail($taskId);
        $memberIds = [
            $task->creator_id,
            $task->requester_id,
            ...$task->assignees->pluck('id')->all(),
            ...$task->watchers->pluck('id')->all(),
        ];

        foreach ($thread as $note) {
            expect($note->user_id)->toBeIn($memberIds);
        }
    }
});

it('leaves no thread behind when the tasks are reseeded', function (): void {
    seedTaskDependencies();
    User::query()->get()->each(fn (User $user) => $user->givePermissionTo('tasks.view'));

    test()->seed(DemoTaskSeeder::class);
    test()->seed(DemoTaskNoteSeeder::class);
    test()->seed(DemoTaskSeeder::class);

    expect(Note::withTrashed()->where('notable_type', (new Task)->getMorphClass())->count())->toBe(0);
});
