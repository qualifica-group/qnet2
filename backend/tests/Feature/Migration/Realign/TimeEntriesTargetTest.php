<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\TimeEntriesTarget;
use App\Models\Registry;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->owner = User::factory()->create(['old_id' => 449]);
    $this->type = TaskType::factory()->create(['name' => 'Chiamata']);
});

function realignTimeEntryRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 90001, 'user_id' => 449, 'date' => '2026-10-25', 'title' => 'Chiamata cliente', 'type_name' => 'Chiamata',
        'start_time' => '09:00', 'end_time' => '09:45', 'minutes' => 45, 'notes' => null,
        'registry_id' => null, 'opportunity_id' => null, 'work_order_id' => null, 'task_id' => null, 'stage_name' => null,
        'warnings' => [], 'created_at' => '2026-10-25 10:00:00', 'updated_at' => '2026-11-01 10:00:00',
    ], $overrides);
}

function realignedTimeEntry(User $owner, array $attributes = []): TimeEntry
{
    $entry = TimeEntry::factory()->create([
        'user_id' => $owner->id, 'date' => '2026-10-25', 'title' => 'Chiamata cliente', 'start_time' => '09:00', 'end_time' => '09:30',
        'minutes' => 30, 'notes' => null, 'task_type_id' => TaskType::query()->where('name', 'Chiamata')->value('id'),
        'registry_id' => null, 'opportunity_id' => null, 'work_order_id' => null, 'task_id' => null, 'work_order_stage_id' => null,
        ...$attributes,
    ]);
    $entry->forceFill(['old_id' => 90001])->saveQuietly();
    Activity::query()->delete();

    return $entry;
}

function runTimeEntriesRealign(bool $apply, array $record): LegacyRealignReport
{
    fakeLegacyRealign('time-entries', [90001], [], [$record]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(TimeEntriesTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

it('aligns minutes, times, notes and links, the owner stays and no row is added (AC-010)', function () {
    $entry = realignedTimeEntry($this->owner);
    $registry = Registry::factory()->create();
    $registry->forceFill(['old_id' => 77])->saveQuietly();
    $otherOwner = User::factory()->create(['old_id' => 450]);

    $report = runTimeEntriesRealign(true, realignTimeEntryRecord([
        'user_id' => 450, 'end_time' => '10:00', 'minutes' => 60, 'notes' => 'Nota nuova', 'registry_id' => 77,
    ]));

    $fresh = $entry->fresh();

    expect($fresh->minutes)->toBe(60)
        ->and(substr((string) $fresh->end_time, 0, 5))->toBe('10:00')
        ->and($fresh->notes)->toBe('Nota nuova')
        ->and($fresh->registry_id)->toBe($registry->id)
        ->and($fresh->user_id)->toBe($this->owner->id)
        ->and($fresh->updated_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00')
        ->and(TimeEntry::query()->count())->toBe(1)
        ->and(array_column($report->changes, 4))->toContain('minutes', 'end_time', 'notes', 'registry_id')
        ->and($otherOwner->id)->not->toBe($fresh->user_id);
});

it('writes nothing when simulating', function () {
    $entry = realignedTimeEntry($this->owner);

    $report = runTimeEntriesRealign(false, realignTimeEntryRecord(['minutes' => 60]));

    expect($entry->fresh()->minutes)->toBe(30)
        ->and($report->counters['time-entries'][LegacyRealignReport::ALIGNED])->toBe(1);
});

it('finds nothing to do when times differ only by the seconds', function () {
    $entry = realignedTimeEntry($this->owner, ['end_time' => '09:45', 'minutes' => 45]);
    DB::table('time_entries')->where('id', $entry->id)->update(['start_time' => '09:00:00', 'end_time' => '09:45:00']);
    $stamp = DB::table('time_entries')->where('id', $entry->id)->value('updated_at');

    $report = runTimeEntriesRealign(true, realignTimeEntryRecord());

    expect($report->counters['time-entries'][LegacyRealignReport::UNCHANGED])->toBe(1)
        ->and(DB::table('time_entries')->where('id', $entry->id)->value('updated_at'))->toBe($stamp);
});

it('keeps a link the legacy sends but qnet-2 cannot resolve', function () {
    $registry = Registry::factory()->create();
    $entry = realignedTimeEntry($this->owner, ['registry_id' => $registry->id]);

    runTimeEntriesRealign(true, realignTimeEntryRecord(['registry_id' => 8888]));

    expect($entry->fresh()->registry_id)->toBe($registry->id);
});

it('fails a duration qnet-2 cannot hold (AC-019)', function () {
    $entry = realignedTimeEntry($this->owner);

    $report = runTimeEntriesRealign(true, realignTimeEntryRecord(['minutes' => 0]));

    expect($entry->fresh()->minutes)->toBe(30)
        ->and($report->counters['time-entries'][LegacyRealignReport::FAILED])->toBe(1);
});
