<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskObserver;
use App\RichText\RichText;
use App\Services\Tasks\TaskOccurrenceFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TaskOccurrenceFactory — materializing ONE occurrence (spec 0120, D-5/D-6/
| D-7/D-14, AC-010..AC-014)
|--------------------------------------------------------------------------
*/

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('systemTaskStatusId')) {
    function systemTaskStatusId(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}

it('AC-010: the occurrence gets the given end_date and a start_date shifted by the same offset as the originator', function () {
    $originator = Task::factory()->create(['start_date' => '2026-03-10', 'end_date' => '2026-03-15']);

    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-03-22'));

    expect($occurrence->end_date->toDateString())->toBe('2026-03-22')
        ->and($occurrence->start_date->toDateString())->toBe('2026-03-17');
});

it('AC-011: an originator with no start_date produces an occurrence with none either, and generation does not fail', function () {
    $originator = Task::factory()->create(['start_date' => null, 'end_date' => '2026-03-15']);

    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-03-22'));

    expect($occurrence->start_date)->toBeNull()
        ->and($occurrence->end_date->toDateString())->toBe('2026-03-22');
});

it('AC-012: the occurrence copies the D-6 scalar fields and pivots, with closure_feedback/completion_date/is_blocked/parent_task_id reset', function () {
    $grandparent = Task::factory()->create();
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();

    $originator = Task::factory()->childOf($grandparent)->create([
        'title' => 'Rinnovo trimestrale',
        'description' => 'Verifica lo stato del contratto',
        'requester_id' => $requester->id,
        'end_date' => '2026-03-15',
        'estimated_minutes' => 45,
        'requires_closure_feedback' => true,
        'closure_feedback' => 'Fatto tutto',
        'is_blocked' => true,
        'completion_date' => '2026-03-14',
    ]);
    $originator->assignees()->sync([$assignee->id]);
    $originator->watchers()->sync([$watcher->id]);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    expect($occurrence->title)->toBe('Rinnovo trimestrale')
        ->and($occurrence->description)->toBe('Verifica lo stato del contratto')
        ->and($occurrence->requester_id)->toBe($requester->id)
        ->and($occurrence->estimated_minutes)->toBe(45)
        ->and($occurrence->requires_closure_feedback)->toBeTrue()
        ->and($occurrence->closure_feedback)->toBeNull()
        ->and($occurrence->completion_date)->toBeNull()
        ->and($occurrence->is_blocked)->toBeFalse()
        ->and($occurrence->parent_task_id)->toBeNull()
        ->and($occurrence->assignees->pluck('id')->all())->toBe([$assignee->id])
        ->and($occurrence->watchers->pluck('id')->all())->toBe([$watcher->id]);
});

// REQUIREMENT CHANGED (spec 0153, D-4): the creator no longer counts — a
// single assignee who is ONLY the creator (no requester on this originator)
// now resolves to the assigned row, not open.
it('AC-013 (spec 0153): a closed originator with a single assignee who is only its creator now produces an occurrence in assigned', function () {
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $creator = User::factory()->create();
    $originator = Task::factory()->inStatus($closedStatus)->forCreator($creator)->create(['end_date' => '2026-03-15']);
    $originator->assignees()->sync([$creator->id]);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    expect($occurrence->task_status_id)->toBe(systemTaskStatusId(TaskStatusSystemKey::Assigned));
});

it('AC-013: the same originator with two assignees produces an occurrence in assigned', function () {
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $creator = User::factory()->create();
    $originator = Task::factory()->inStatus($closedStatus)->forCreator($creator)->create(['end_date' => '2026-03-15']);
    $originator->assignees()->sync([$creator->id, User::factory()->create()->id]);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    expect($occurrence->task_status_id)->toBe(systemTaskStatusId(TaskStatusSystemKey::Assigned));
});

it('AC-014: the occurrence is not a sub-task, and the originator stays deletable once it has none of its own', function () {
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    expect($occurrence->parent_task_id)->toBeNull()
        ->and($originator->subtasks()->exists())->toBeFalse();
});

it('AC-012 (spec 0128, D-8): the occurrence gets its own copy of the originator\'s rich_text image, with the NEW id', function () {
    Storage::fake(config('attachments.disk'));
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);

    $original = Attachment::factory()->make(['collection' => RichText::ATTACHMENT_COLLECTION]);
    $original->attachable()->associate($originator);
    $original->save();
    Storage::disk($original->disk)->put($original->path, 'fake-bytes');

    $originator->description = '<p>x</p><img data-attachment-id="'.$original->id.'" alt="pic">';
    $originator->save();

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    $copy = Attachment::query()
        ->where('attachable_type', $occurrence->getMorphClass())
        ->where('attachable_id', $occurrence->id)
        ->where('collection', RichText::ATTACHMENT_COLLECTION)
        ->sole();

    expect($copy->id)->not->toBe($original->id)
        ->and($occurrence->description)->toBe('<p>x</p><img data-attachment-id="'.$copy->id.'" alt="pic">');
});

it('D-14: the occurrence notifies its assignees and watchers with the system as actor', function () {
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $originator->assignees()->sync([$assignee->id]);
    $originator->watchers()->sync([$watcher->id]);

    Notification::fake();
    app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    Notification::assertSentTo($assignee, TaskAssigned::class);
    Notification::assertSentTo($watcher, TaskObserver::class);
});

it('AC-004 (spec 0155, D-2): the occurrence copies the originator\'s direct sub-task, dates shifted by the same offset, position preserved, status re-derived', function () {
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();

    $subtask = Task::factory()->childOf($originator)->create([
        'title' => 'Verifica documenti',
        'requester_id' => $requester->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-12',
        'subtask_position' => 3,
    ]);
    $subtask->assignees()->sync([$assignee->id]);
    $subtask->watchers()->sync([$watcher->id]);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    $copiedSubtask = $occurrence->subtasks()->sole();

    expect($copiedSubtask->title)->toBe('Verifica documenti')
        ->and($copiedSubtask->requester_id)->toBe($requester->id)
        ->and($copiedSubtask->start_date->toDateString())->toBe('2026-04-10')
        ->and($copiedSubtask->end_date->toDateString())->toBe('2026-04-12')
        ->and($copiedSubtask->subtask_position)->toBe(3)
        ->and($copiedSubtask->parent_task_id)->toBe($occurrence->id)
        ->and($copiedSubtask->assignees->pluck('id')->all())->toBe([$assignee->id])
        ->and($copiedSubtask->watchers->pluck('id')->all())->toBe([$watcher->id])
        ->and($copiedSubtask->task_status_id)->toBe(systemTaskStatusId(TaskStatusSystemKey::Assigned));
});

it('AC-004 (spec 0155, D-2): a sub-task without dates of its own produces a copy with none either', function () {
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);
    Task::factory()->childOf($originator)->create(['start_date' => null, 'end_date' => null]);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    $copiedSubtask = $occurrence->subtasks()->sole();

    expect($copiedSubtask->start_date)->toBeNull()
        ->and($copiedSubtask->end_date)->toBeNull();
});

// REQUIREMENT CHANGED (spec 0161, D-3): the recurrence now copies the WHOLE
// sub-task tree, every level — a sub-task of a sub-task is no longer
// dropped, it is copied too, with the same date shift and its own copy as
// the new direct parent.
it('AC-006 (spec 0161): the occurrence copies a grandchild sub-task too, dates shifted, under its own copied parent', function () {
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);
    $subtask = Task::factory()->childOf($originator)->create();
    $grandchild = Task::factory()->childOf($subtask)->create([
        'title' => 'Nipote',
        'start_date' => '2026-03-11',
        'end_date' => '2026-03-13',
    ]);

    Notification::fake();
    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    $copiedSubtask = $occurrence->subtasks()->sole();
    $copiedGrandchild = $copiedSubtask->subtasks()->sole();

    expect($copiedGrandchild->title)->toBe('Nipote')
        ->and($copiedGrandchild->parent_task_id)->toBe($copiedSubtask->id)
        ->and($copiedGrandchild->start_date->toDateString())->toBe('2026-04-11')
        ->and($copiedGrandchild->end_date->toDateString())->toBe('2026-04-13')
        ->and($copiedGrandchild->id)->not->toBe($grandchild->id);
});
