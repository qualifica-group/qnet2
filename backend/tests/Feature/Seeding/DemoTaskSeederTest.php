<?php

use App\Enums\TaskStatusGroup;
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
use App\Models\WorkOrder;
use Database\Seeders\DemoTaskNoteSeeder;
use Database\Seeders\DemoTaskSeeder;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

/**
 * Merged scenario: every former test paid seedTaskDependencies() (two
 * seeders plus factories) AND DemoTaskSeeder again on its own, some of them
 * twice for an idempotency check. None of the read-only assertions mutate
 * what they read, and the seeding sequences nest into one another exactly —
 * seed tasks, seed notes, reseed tasks — so the whole file now runs the
 * dependencies once and the task/note seeders three times total instead of
 * eleven.
 */
it('seeds the demo tasks and their note threads, staying idempotent on a reseed', function (): void {
    // Step 1: shared dependencies, once for every assertion below. The
    // permission grant and the mail counter are no-ops for the tests that do
    // not care about them.
    seedTaskDependencies();
    User::query()->get()->each(fn (User $user) => $user->givePermissionTo('tasks.view'));
    $mailsSent = 0;
    Event::listen(MessageSending::class, function () use (&$mailsSent): void {
        $mailsSent++;
    });

    // Step 2: seed the tasks once — every single-run check reads this state.
    test()->seed(DemoTaskSeeder::class);

    // was: 'seeds the tasks hierarchy with both membership pivots'
    $tasksWithMembers = Task::query()->with(['assignees', 'watchers'])->get();

    expect($tasksWithMembers)->toHaveCount(60)
        ->and($tasksWithMembers->whereNotNull('parent_task_id'))->toHaveCount(20)
        ->and($tasksWithMembers->every(fn (Task $task): bool => $task->creator_id !== null))->toBeTrue()
        ->and($tasksWithMembers->every(fn (Task $task): bool => $task->assignees->isNotEmpty()))->toBeTrue()
        ->and($tasksWithMembers->contains(fn (Task $task): bool => $task->watchers->isNotEmpty()))->toBeTrue()
        ->and($tasksWithMembers->contains(fn (Task $task): bool => $task->is_blocked))->toBeTrue();

    // was: 'classifies the tasks on the seeded vocabulary, never on an invented one'
    $tasksForClassification = Task::query()->get();

    expect(TaskType::count())->toBeGreaterThan(0)
        ->and($tasksForClassification->whereNotNull('task_type_id'))->not->toBeEmpty()
        ->and($tasksForClassification->whereNotNull('task_category_id'))->not->toBeEmpty()
        ->and($tasksForClassification->whereNotNull('task_priority_id'))->not->toBeEmpty()
        ->and($tasksForClassification->whereNotNull('task_importance_id'))->not->toBeEmpty();

    foreach ($tasksForClassification as $task) {
        expect($task->task_status_id)->toBeIn(TaskStatus::query()->pluck('id')->all())
            ->and($task->task_type_id)->toBeIn([null, ...TaskType::query()->pluck('id')->all()])
            ->and($task->task_category_id)->toBeIn([null, ...TaskCategory::query()->pluck('id')->all()])
            ->and($task->task_priority_id)->toBeIn([null, ...TaskPriority::query()->pluck('id')->all()])
            ->and($task->task_importance_id)->toBeIn([null, ...TaskImportance::query()->pluck('id')->all()]);
    }

    // was: 'goes through the real write path: referente coherence and closing dates hold'
    $tasksWithStatus = Task::query()->with('taskStatus')->get();

    expect($tasksWithStatus->whereNotNull('referent_id'))->not->toBeEmpty();

    foreach ($tasksWithStatus as $task) {
        if ($task->referent_id !== null) {
            // AC-014: the referente belongs to the Task's own anagrafica.
            expect(DB::table('referent_registry')
                ->where('referent_id', $task->referent_id)
                ->where('registry_id', $task->registry_id)
                ->exists())->toBeTrue($task->title);
        }

        // REQUIREMENT CHANGED (spec 0127, D-4): only the phases the Complete
        // action reaches (in_validation, closed_positive) carry a completion
        // date; closed_negative and the open phases carry none. Finished work
        // is never dated in the future.
        $hasCompletionDate = in_array($task->taskStatus->group, [TaskStatusGroup::InValidation, TaskStatusGroup::ClosedPositive], true);
        expect($task->completion_date !== null)->toBe($hasCompletionDate, $task->title);

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

    // Requirement changed (user directive 2026-09-29): the seed leaves the
    // in-app notifications of the real write path, but sends no email.
    expect(Task::count())->toBe(60)
        ->and(DB::table('notifications')->count())->toBeGreaterThan(0)
        ->and($mailsSent)->toBe(0);

    // Step 3: seed the note threads on top of that first task run.
    test()->seed(DemoTaskNoteSeeder::class);

    // was: 'seeds task threads written only by members who may read the task'
    $taskNotes = Note::query()->where('notable_type', (new Task)->getMorphClass())->get();

    expect($taskNotes)->not->toBeEmpty()
        ->and($taskNotes->whereNotNull('parent_id'))->not->toBeEmpty()
        // D-6: a Task has no scoping unit, so no note on it carries one.
        ->and($taskNotes->whereNotNull('quote_id'))->toBeEmpty();

    foreach ($taskNotes->groupBy('notable_id') as $taskId => $thread) {
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

    // Step 4: reseed the tasks — both re-run checks land on this state.
    // was: 'is idempotent: a second run replaces the dataset instead of piling onto it'
    // was: 'leaves no thread behind when the tasks are reseeded'
    test()->seed(DemoTaskSeeder::class);

    expect(Task::count())->toBe(60)
        ->and(DB::table('task_assignee')->count())->toBeGreaterThan(0)
        ->and(DB::table('task_watcher')->count())->toBeGreaterThan(0);

    expect(Note::withTrashed()->where('notable_type', (new Task)->getMorphClass())->count())->toBe(0);
});

it('spec 0154 D-11: with commesse and opportunita\' seeded, no demo task links both', function (): void {
    seedTaskDependencies();
    WorkOrder::factory()->count(5)->create();

    test()->seed(DemoTaskSeeder::class);

    $tasks = Task::query()->get();

    expect($tasks->contains(fn (Task $task): bool => $task->work_order_id !== null && $task->opportunity_id !== null))->toBeFalse()
        ->and($tasks->whereNotNull('work_order_id'))->not->toBeEmpty()
        ->and($tasks->whereNotNull('opportunity_id'))->not->toBeEmpty();
});
