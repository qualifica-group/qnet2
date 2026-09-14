<?php

use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET stats/overview and stats/pulse (spec 0122, D-11, AC-017/AC-018)
|--------------------------------------------------------------------------
| Fixed Mon 2026-09-14 .. Sun 2026-09-20 period, no employment profile
| (target_minutes = config default 480 on every working day):
|   Mon 2026-09-14: 500min typeA (over)   Tue 2026-09-15: 100min typeA (under)
|   Wed 2026-09-16: 480min typeB (on)     Thu/Fri: nothing (under, 0<480)
|   Sat/Sun: nothing (non-working)        Sun 2026-09-20: 60min typeA (non-working)
*/

if (! function_exists('timeEntryActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function timeEntryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

function seedTimeEntryStatsFixture(User $actor, TaskType $typeA, TaskType $typeB): void
{
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create(['task_type_id' => $typeA->id, 'minutes' => 500]);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-15')->create(['task_type_id' => $typeA->id, 'minutes' => 100]);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-16')->create(['task_type_id' => $typeB->id, 'minutes' => 480]);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-20')->create(['task_type_id' => $typeA->id, 'minutes' => 60]); // Sunday
}

// ---------------------------------------------------------------------------
// AC-017 — overview
// ---------------------------------------------------------------------------

it('AC-017: overview includes the Sunday in tracked_minutes, counts only ACTIVE filtered days in tracked_days, working-day target/average/anomalies per D-11', function () {
    $actor = timeEntryActorWith(['viewAny']);
    [$typeA, $typeB] = TaskType::factory()->count(2)->create();
    seedTimeEntryStatsFixture($actor, $typeA, $typeB);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries/stats/overview?date_from=2026-09-14&date_to=2026-09-20')->assertOk();
    $data = $response->json('data');

    expect($data['period'])->toBe(['date_from' => '2026-09-14', 'date_to' => '2026-09-20'])
        ->and($data['tracked_minutes'])->toBe(1140) // 500+100+480+0+0+0+60, Sunday INCLUDED
        ->and($data['tracked_days'])->toBe(4) // Mon/Tue/Wed/Sun HAVE a segnatempo; Thu/Fri/Sat don't count (correction: was 7, fixed after review)
        ->and($data['period_target_minutes'])->toBe(2400) // 5 working days * 480
        ->and($data['working_days'])->toBe(5)
        ->and($data['daily_target_minutes'])->toBe(480) // last working day (Friday) of the period
        ->and($data['average_daily_focus_minutes'])->toBe(285) // round(1140/4), was 163 before the fix
        ->and($data['anomalies'])->toBe(['over_target_days' => 1, 'under_target_days' => 3]);
});

it('AC-017: daily_target_minutes falls back to "target di oggi" (the profile figure, calendar-agnostic) when the period has no working day', function () {
    // Decision: "target di oggi" reuses the SAME calendar-agnostic profile
    // figure as meta.daily_target_minutes ("anche se oggi e' festivo usa il
    // valore da profilo") — TimeEntryDaySet::baseTargetMinutes, not a
    // fresh lookup pinned to the real wall-clock date.
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    // 2026-09-19/20 is a Sat/Sun only period: zero working days.
    $response = $this->getJson('/api/time-entries/stats/overview?date_from=2026-09-19&date_to=2026-09-20')->assertOk();

    expect($response->json('data.working_days'))->toBe(0)
        ->and($response->json('data.daily_target_minutes'))->toBe(480);
});

// ---------------------------------------------------------------------------
// AC-018 — pulse
// ---------------------------------------------------------------------------

it('AC-018: pulse coverage excludes the Sunday, primary_cluster is the top task type, other_clusters descending', function () {
    $actor = timeEntryActorWith(['viewAny']);
    $typeA = TaskType::factory()->create(['name' => 'Type A']);
    $typeB = TaskType::factory()->create(['name' => 'Type B']);
    seedTimeEntryStatsFixture($actor, $typeA, $typeB);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries/stats/pulse?date_from=2026-09-14&date_to=2026-09-20')->assertOk();
    $data = $response->json('data');

    expect($data['coverage'])->toBe(['percentage' => 45, 'working_days' => 5, 'tracked_days' => 3]) // 1080/2400, Sunday excluded
        ->and($data['primary_cluster']['task_type']['id'])->toBe($typeA->id)
        ->and($data['primary_cluster']['minutes'])->toBe(600) // 500 + 100, Sunday's 60 excluded
        ->and($data['primary_cluster']['percentage'])->toBe(56)
        ->and($data['other_clusters'])->toHaveCount(1)
        ->and($data['other_clusters'][0]['task_type']['id'])->toBe($typeB->id)
        ->and($data['other_clusters'][0]['minutes'])->toBe(480)
        ->and($data['other_clusters'][0]['percentage'])->toBe(44);
});

it('AC-018: with no segnatempo at all, primary_cluster is null, other_clusters empty, coverage 0', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries/stats/pulse?date_from=2026-09-14&date_to=2026-09-20')->assertOk();

    expect($response->json('data.primary_cluster'))->toBeNull()
        ->and($response->json('data.other_clusters'))->toBe([])
        ->and($response->json('data.coverage.percentage'))->toBe(0);
});

it('AC-018: pulse ignores daily_statuses/is_active filters entirely (D-11)', function () {
    $actor = timeEntryActorWith(['viewAny']);
    [$typeA, $typeB] = TaskType::factory()->count(2)->create();
    seedTimeEntryStatsFixture($actor, $typeA, $typeB);
    Sanctum::actingAs($actor);

    // is_active=false would, on the list endpoint, keep only EMPTY days —
    // pulse must still see the tracked minutes.
    $response = $this->getJson(
        '/api/time-entries/stats/pulse?date_from=2026-09-14&date_to=2026-09-20&is_active=false&daily_statuses[]=no_target'
    )->assertOk();

    expect($response->json('data.coverage.tracked_days'))->toBe(3);
});
