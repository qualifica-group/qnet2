<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\TasksTarget;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskRecurrence;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->creator = legacyTaskCreator();
});

function realignedTask(array $attributes = []): Task
{
    $task = Task::factory()->create(['title' => 'Before', ...$attributes]);
    $task->forceFill(['old_id' => 500])->saveQuietly();
    Activity::query()->delete();

    return $task;
}

function runTasksRealign(bool $apply, array $record): LegacyRealignReport
{
    fakeLegacyRealign('tasks', [500], [], [$record]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(TasksTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

function changedTaskFields(LegacyRealignReport $report): array
{
    return array_column($report->changes, 4);
}

it('aligns assignees and watchers and leaves creator, parent and series (AC-009)', function () {
    $parent = Task::factory()->create();
    $series = TaskRecurrence::factory()->create();
    $task = realignedTask(['parent_task_id' => $parent->id]);
    $task->forceFill(['task_recurrence_id' => $series->id])->saveQuietly();
    $creatorId = $task->fresh()->creator_id;
    [$assignee, $watcher, $dropped] = User::factory()->count(3)->create();
    $assignee->forceFill(['old_id' => 61, 'name' => 'Ada Rossi'])->save();
    $watcher->forceFill(['old_id' => 62, 'name' => 'Bea Verdi'])->save();
    $task->assignees()->sync([$dropped->id]);
    $activityBefore = Activity::query()->count();

    $report = runTasksRealign(true, legacyTaskRecord([
        'title' => 'After',
        'assignee_user_ids' => [61],
        'watcher_user_ids' => [62],
        'creator_user_id' => 60,
        'parent_id' => 999,
        'updated_at' => '2026-11-01 10:00:00',
    ]));

    $fresh = $task->fresh();

    expect($fresh->title)->toBe('After')
        ->and($fresh->assignees()->pluck('users.id')->all())->toBe([$assignee->id])
        ->and($fresh->watchers()->pluck('users.id')->all())->toBe([$watcher->id])
        ->and($fresh->creator_id)->toBe($creatorId)
        ->and($fresh->parent_task_id)->toBe($parent->id)
        ->and($fresh->task_recurrence_id)->toBe($series->id)
        ->and($fresh->updated_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00')
        ->and($report->counters['tasks'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and(Activity::query()->count())->toBe($activityBefore);

    expect(collect($report->changes)->firstWhere(4, 'assignees')[6])->toBe('Ada Rossi');
});

it('aligns the template generated flag (spec 0388, AC-005)', function () {
    $task = realignedTask();

    $report = runTasksRealign(true, legacyTaskRecord(['is_template_generated' => true]));

    expect($task->fresh()->is_template_generated)->toBeTrue()
        ->and(changedTaskFields($report))->toContain('is_template_generated');
});

it('writes nothing when simulating (AC-001)', function () {
    $task = realignedTask();

    $report = runTasksRealign(false, legacyTaskRecord(['title' => 'After']));

    expect($task->fresh()->title)->toBe('Before')
        ->and(changedTaskFields($report))->toContain('title');
});

it('keeps a status, type and priority the legacy does not decide', function () {
    $status = TaskStatus::factory()->create();
    $type = TaskType::factory()->create();
    $priority = TaskPriority::factory()->create();
    $task = realignedTask(['title' => 'Same', 'task_status_id' => $status->id, 'task_type_id' => $type->id, 'task_priority_id' => $priority->id]);

    $report = runTasksRealign(true, legacyTaskRecord([
        'title' => 'Same',
        'status_system_key' => 'not-a-key',
        'priority_name' => 'Unknown one',
        'type_name' => null,
    ]));

    $fresh = $task->fresh();

    expect($fresh->task_status_id)->toBe($status->id)
        ->and($fresh->task_type_id)->toBe($type->id)
        ->and($fresh->task_priority_id)->toBe($priority->id)
        ->and(changedTaskFields($report))->not->toContain('status', 'type', 'priority');
});

it('applies a status the legacy names', function () {
    $task = realignedTask(['title' => 'Same']);
    $target = TaskStatus::factory()->create(['name' => 'In lavorazione']);

    runTasksRealign(true, legacyTaskRecord(['title' => 'Same', 'status_name' => 'in lavorazione']));

    expect($task->fresh()->task_status_id)->toBe($target->id);
});

it('does not see a time stored with seconds as a change', function () {
    $task = realignedTask(['title' => 'Same']);
    DB::table('tasks')->where('id', $task->id)->update(['start_time' => '09:00:00', 'end_time' => '10:30:00', 'updated_at' => '2026-01-01 00:00:00']);

    $report = runTasksRealign(true, legacyTaskRecord([
        'title' => 'Same',
        'description' => $task->description,
        'start_date' => $task->start_date?->toDateString(),
        'end_date' => $task->end_date?->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:30',
        'estimated_minutes' => $task->estimated_minutes,
        'updated_at' => '2026-11-01 10:00:00',
        'requester_user_id' => null,
    ]));

    expect(changedTaskFields($report))->not->toContain('start_time', 'end_time')
        ->and(DB::table('tasks')->where('id', $task->id)->value('start_time'))->toBe('09:00:00');
});

it('fails the row and writes nothing when a person is not migrated (AC-019)', function () {
    $task = realignedTask();

    $report = runTasksRealign(true, legacyTaskRecord(['title' => 'After', 'assignee_user_ids' => [404]]));

    expect($task->fresh()->title)->toBe('Before')
        ->and($report->counters['tasks'][LegacyRealignReport::FAILED])->toBe(1)
        ->and($report->messages[0][3])->toContain('legacy user id 404');
});

it('stops with exit 1 and no write when the legacy is unreachable (AC-020)', function () {
    $task = realignedTask();
    Http::fake([fakeMigrationsBaseUrl().'/*' => Http::response('down', 500)]);
    $path = legacyRealignOutputPath();

    $this->artisan('migrations:realign-legacy', ['--since' => '2026-10-23', '--sources' => 'tasks', '--apply' => true, '--output' => $path])
        ->assertExitCode(1);

    expect($task->fresh()->title)->toBe('Before');
    @unlink($path);
});
