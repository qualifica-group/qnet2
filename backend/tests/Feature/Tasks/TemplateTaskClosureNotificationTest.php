<?php

use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskClosed;
use App\Notifications\TaskFeedbackInserted;
use App\Services\Notifications\TaskNotificationAudience;
use App\Services\Notifications\TaskNotifier;
use App\Services\WorkOrders\WorkOrderTaskGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
| Spec 0388: the closing notification of a Task generated from a Modello di
| Task skips the requester, who is only whoever generated the commessa.
*/

/**
 * @return array{0: Task, 1: array<string, User>}
 */
function templateClosureFixture(bool $isTemplateGenerated): array
{
    $people = [
        'requester' => User::factory()->create(),
        'assignee' => User::factory()->create(),
        'watcher' => User::factory()->create(),
    ];

    $task = Task::factory()->create([
        'creator_id' => $people['requester']->id,
        'requester_id' => $people['requester']->id,
    ]);
    $task->forceFill(['is_template_generated' => $isTemplateGenerated])->save();
    $task->assignees()->attach($people['assignee']->id);
    $task->watchers()->attach($people['watcher']->id);

    return [$task->fresh(), $people];
}

it('AC-001: closing a template-generated task skips the requester and keeps the watchers', function () {
    Notification::fake();
    [$task, $people] = templateClosureFixture(true);

    app(TaskNotifier::class)->closed($task, $people['assignee'], false);
    app(TaskNotifier::class)->feedbackInserted($task, $people['assignee'], false);

    Notification::assertNotSentTo($people['requester'], TaskClosed::class);
    Notification::assertNotSentTo($people['requester'], TaskFeedbackInserted::class);
    Notification::assertSentTo($people['watcher'], TaskClosed::class);
    Notification::assertSentTo($people['watcher'], TaskFeedbackInserted::class);
});

it('AC-002: closing a task not generated from a template still notifies the requester', function () {
    Notification::fake();
    [$task, $people] = templateClosureFixture(false);

    app(TaskNotifier::class)->closed($task, $people['assignee'], false);

    Notification::assertSentTo([$people['requester'], $people['watcher']], TaskClosed::class);
});

it('AC-003: a requester who also watches a template-generated task still hears about its closure', function () {
    $audience = new TaskNotificationAudience(1, 2, [3], [2, 4], isTemplateGenerated: true);

    expect($audience->closure(null, includeAssignees: false))->toEqualCanonicalizing([2, 4])
        ->and($audience->closure(null, includeAssignees: true))->toEqualCanonicalizing([2, 3, 4]);
});

it('AC-003: the requester of a template-generated task keeps every other audience', function () {
    $audience = new TaskNotificationAudience(1, 2, [3], [4], isTemplateGenerated: true);

    expect($audience->closure(null, includeAssignees: true))->toEqualCanonicalizing([3, 4])
        ->and($audience->requesterOrCreator(null))->toBe([2])
        ->and($audience->everyone(null))->toEqualCanonicalizing([1, 2, 3, 4]);
});

it('AC-004: the generator marks roots and sub-tasks as template generated, a manual task is not', function () {
    $actor = User::factory()->create();
    $supervisor = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id, 'start_date' => '2026-10-01']);
    $workOrder->supervisors()->sync([$supervisor->id]);
    $template = TaskTemplate::factory()->create();
    $root = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Root', 'due_offset_days' => 1]);
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['title' => 'Child', 'due_offset_days' => 1, 'parent_id' => $root->id]);

    $tasks = app(WorkOrderTaskGenerator::class)->generate($workOrder, $template, $actor);

    expect($tasks)->toHaveCount(2)
        ->and(Task::query()->where('work_order_id', $workOrder->id)->pluck('is_template_generated')->all())->toBe([true, true])
        ->and(Task::factory()->create()->fresh()->is_template_generated)->toBeFalse();
});
