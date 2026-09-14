<?php

use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\TimeEntryDayNote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| TimeEntry/TimeEntryDayNote model, factory and schema constraints (spec 0122)
|--------------------------------------------------------------------------
*/

it('creates a minimal time entry through the factory with its relations resolvable', function () {
    $user = User::factory()->create();
    $taskType = TaskType::factory()->create();

    $entry = TimeEntry::factory()->forUser($user)->onDate('2026-09-10')->create([
        'task_type_id' => $taskType->id,
        'title' => 'Sviluppo',
        'minutes' => 90,
    ]);

    expect($entry->user->is($user))->toBeTrue()
        ->and($entry->taskType->is($taskType))->toBeTrue()
        ->and($entry->date->format('Y-m-d'))->toBe('2026-09-10')
        ->and($entry->minutes)->toBe(90)
        ->and($entry->registry)->toBeNull()
        ->and($entry->opportunity)->toBeNull()
        ->and($entry->workOrder)->toBeNull()
        ->and($entry->task)->toBeNull();
});

it('derives minutes from the interval via withInterval()', function () {
    $entry = TimeEntry::factory()->withInterval('09:00', '10:30')->create();

    expect($entry->minutes)->toBe(90)
        ->and($entry->start_time)->toBe('09:00')
        ->and($entry->end_time)->toBe('10:30');
});

it('cascades the deletion of the owning user to their time entries', function () {
    $user = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($user)->create();

    $user->delete();

    expect(TimeEntry::query()->whereKey($entry->id)->exists())->toBeFalse();
});

it('restricts deleting a task type still referenced by a time entry', function () {
    $taskType = TaskType::factory()->create();
    TimeEntry::factory()->create(['task_type_id' => $taskType->id]);

    expect(fn () => $taskType->delete())->toThrow(QueryException::class);
});

it('enforces one day note per user per date', function () {
    $user = User::factory()->create();
    TimeEntryDayNote::factory()->forUser($user)->onDate('2026-09-10')->create();

    expect(fn () => TimeEntryDayNote::factory()->forUser($user)->onDate('2026-09-10')->create())
        ->toThrow(QueryException::class);
});

it('allows the same user a day note on a different date', function () {
    $user = User::factory()->create();
    TimeEntryDayNote::factory()->forUser($user)->onDate('2026-09-10')->create();
    $second = TimeEntryDayNote::factory()->forUser($user)->onDate('2026-09-11')->create();

    expect(TimeEntryDayNote::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and($second->date->format('Y-m-d'))->toBe('2026-09-11');
});
