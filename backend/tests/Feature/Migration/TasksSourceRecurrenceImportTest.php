<?php

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Models\Task;
use App\Models\TaskRecurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The legacy task fixtures live in tests/Helpers/TaskMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    legacyTaskCreator();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyWeeklyRecurrence(array $overrides = []): array
{
    return array_merge([
        'frequency' => 'weekly', 'interval' => 2, 'weekdays' => [1, 3], 'month_day' => null,
        'month_mode' => null, 'ordinal' => null, 'ordinal_weekday' => null, 'year_month' => null,
        'workdays_only' => true, 'ends' => 'after_count', 'ends_on' => null, 'occurrence_count' => 8,
        'generated_until' => '2024-06-10',
    ], $overrides);
}

it('AC-012 creates the series from the origin rule and links the origin to it', function () {
    $run = runLegacyTasksImport([legacyTaskRecord([
        'end_date' => '2024-05-10',
        'recurrence' => legacyWeeklyRecurrence(),
    ])]);

    $task = Task::query()->where('old_id', 500)->sole();
    $recurrence = TaskRecurrence::query()->sole();

    expect($task->task_recurrence_id)->toBe($recurrence->id)
        ->and($recurrence->frequency)->toBe(TaskRecurrenceFrequency::Weekly)
        ->and($recurrence->interval)->toBe(2)
        ->and($recurrence->weekdays)->toBe([1, 3])
        ->and($recurrence->workdays_only)->toBeTrue()
        ->and($recurrence->ends)->toBe(TaskRecurrenceEnd::AfterCount)
        ->and($recurrence->occurrence_count)->toBe(8)
        ->and($recurrence->generated_until->toDateString())->toBe('2024-06-10')
        ->and($run->report)->toBeNull();
});

it('AC-012 imports the task without series and warns on an invalid rule or a missing end date', function () {
    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 501, 'end_date' => '2024-05-10', 'recurrence' => legacyWeeklyRecurrence(['frequency' => 'fortnightly'])]),
        legacyTaskRecord(['id' => 502, 'end_date' => '2024-05-10', 'recurrence' => legacyWeeklyRecurrence(['ends' => 'sometime'])]),
        legacyTaskRecord(['id' => 503, 'end_date' => '2024-05-10', 'recurrence' => legacyWeeklyRecurrence(['month_mode' => 'weird'])]),
        legacyTaskRecord(['id' => 504, 'end_date' => null, 'recurrence' => legacyWeeklyRecurrence()]),
    ]);

    expect(Task::query()->count())->toBe(4)
        ->and(Task::query()->whereNotNull('task_recurrence_id')->count())->toBe(0)
        ->and(TaskRecurrence::query()->count())->toBe(0)
        ->and(legacyTaskWarnings($run))->toBe([
            'Recurrence not imported: frequency, month mode or end type outside the supported values.',
            'Recurrence not imported: frequency, month mode or end type outside the supported values.',
            'Recurrence not imported: frequency, month mode or end type outside the supported values.',
            'Recurrence not imported: the series origin has no end date.',
        ]);
});

it('AC-013 links an occurrence to the series of its origin', function () {
    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 500, 'end_date' => '2024-05-10', 'recurrence' => legacyWeeklyRecurrence()]),
        legacyTaskRecord(['id' => 501, 'end_date' => '2024-05-24', 'recurrence_source_id' => 500]),
        legacyTaskRecord(['id' => 502, 'end_date' => '2024-06-07', 'recurrence_source_id' => 500]),
    ]);

    $seriesId = Task::query()->where('old_id', 500)->value('task_recurrence_id');

    expect($seriesId)->not->toBeNull()
        ->and(Task::query()->where('old_id', 501)->value('task_recurrence_id'))->toBe($seriesId)
        ->and(Task::query()->where('old_id', 502)->value('task_recurrence_id'))->toBe($seriesId)
        ->and(TaskRecurrence::query()->count())->toBe(1)
        ->and($run->report)->toBeNull();
});

it('AC-013 imports an occurrence without series when its origin has none or the end date is already in the series', function () {
    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 500, 'end_date' => '2024-05-10', 'recurrence' => legacyWeeklyRecurrence()]),
        legacyTaskRecord(['id' => 501, 'end_date' => '2024-05-10', 'recurrence_source_id' => 500]),
        legacyTaskRecord(['id' => 502, 'end_date' => '2024-05-24', 'recurrence_source_id' => 700]),
        legacyTaskRecord(['id' => 503, 'end_date' => '2024-05-25', 'recurrence_source_id' => 502]),
    ]);

    $seriesOf = fn (int $oldId): ?int => Task::query()->where('old_id', $oldId)->value('task_recurrence_id');

    expect($run->failed_rows)->toBe(0)
        ->and($seriesOf(501))->toBeNull()
        ->and($seriesOf(502))->toBeNull()
        ->and($seriesOf(503))->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe([
            'Occurrence imported without series: the series already has a task ending on 2024-05-10.',
            'Occurrence imported without series: its origin (legacy id 700) has no series in qnet-2.',
            'Occurrence imported without series: its origin (legacy id 502) has no series in qnet-2.',
        ]);
});
