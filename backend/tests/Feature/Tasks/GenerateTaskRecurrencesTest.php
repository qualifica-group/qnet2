<?php

use App\Enums\TaskRecurrenceFrequency;
use App\Models\Task;
use App\Models\TaskRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| `tasks:generate-recurrences` (spec 0120, D-8/D-9, AC-015..AC-021)
|--------------------------------------------------------------------------
*/

if (! function_exists('today0120')) {
    function today0120(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }
}

if (! function_exists('dailyRecurrenceWithOriginator')) {
    function dailyRecurrenceWithOriginator(CarbonImmutable $originatorEndDate): TaskRecurrence
    {
        $recurrence = TaskRecurrence::factory()->create(['frequency' => TaskRecurrenceFrequency::Daily->value]);

        $originator = Task::factory()->create(['end_date' => $originatorEndDate->toDateString()]);
        $originator->task_recurrence_id = $recurrence->id;
        $originator->save();

        return $recurrence->fresh();
    }
}

it('AC-015: backdated occurrences are created in chronological order, none in the future', function () {
    Notification::fake();
    $recurrence = dailyRecurrenceWithOriginator(today0120()->subDays(5));

    $this->artisan('tasks:generate-recurrences')->assertSuccessful();

    $endDates = $recurrence->tasks()->orderBy('end_date')->pluck('end_date')->map(fn ($d) => $d->toDateString())->all();

    expect($endDates)->toBe([
        today0120()->subDays(5)->toDateString(),
        today0120()->subDays(4)->toDateString(),
        today0120()->subDays(3)->toDateString(),
        today0120()->subDays(2)->toDateString(),
        today0120()->subDays(1)->toDateString(),
        today0120()->toDateString(),
    ]);
});

it('AC-016: running the command twice creates nothing the second time (D-9)', function () {
    Notification::fake();
    dailyRecurrenceWithOriginator(today0120()->subDays(3));

    $this->artisan('tasks:generate-recurrences')->assertSuccessful();
    $countAfterFirst = Task::query()->count();

    $this->artisan('tasks:generate-recurrences')->assertSuccessful();

    expect(Task::query()->count())->toBe($countAfterFirst);
});

it('AC-017: a direct duplicate (task_recurrence_id, end_date) insert is rejected by the UNIQUE constraint', function () {
    $recurrence = TaskRecurrence::factory()->create();
    $existing = Task::factory()->create(['end_date' => '2026-05-10']);
    $existing->task_recurrence_id = $recurrence->id;
    $existing->save();

    $duplicateRow = $existing->getAttributes();
    unset($duplicateRow['id']);

    expect(fn () => DB::table('tasks')->insert($duplicateRow))->toThrow(QueryException::class);
});

it('AC-018: --dry-run reports without writing anything', function () {
    Notification::fake();
    $recurrence = dailyRecurrenceWithOriginator(today0120()->subDays(2));
    $countBefore = Task::query()->count();

    $this->artisan('tasks:generate-recurrences', ['--dry-run' => true])->assertSuccessful();

    expect(Task::query()->count())->toBe($countBefore)
        ->and($recurrence->fresh()->generated_until)->toBeNull();
});

it('AC-019: --recurrence limits generation to a single series', function () {
    Notification::fake();
    $target = dailyRecurrenceWithOriginator(today0120()->subDays(2));
    $other = dailyRecurrenceWithOriginator(today0120()->subDays(2));

    $this->artisan('tasks:generate-recurrences', ['--recurrence' => $target->id])->assertSuccessful();

    expect($target->tasks()->count())->toBe(3)
        ->and($other->tasks()->count())->toBe(1);
});

it('AC-020: with nothing due yet, the command exits 0 and writes nothing', function () {
    Notification::fake();
    dailyRecurrenceWithOriginator(today0120()->addDay());
    $countBefore = Task::query()->count();

    $this->artisan('tasks:generate-recurrences')->assertSuccessful();

    expect(Task::query()->count())->toBe($countBefore);
});

it('AC-021: routes/console.php schedules tasks:generate-recurrences daily with withoutOverlapping', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'tasks:generate-recurrences'));

    expect($events)->toHaveCount(1);

    $event = $events->first();

    expect($event->expression)->toBe('0 1 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
