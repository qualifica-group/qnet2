<?php

use App\Models\EmploymentProfile;
use App\Models\Registry;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/time-entries (spec 0122, MT-B3, AC-010..AC-015, AC-021 day_note)
|--------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// AC-010 — one DaySummary per date, is_active only where entries exist
// ---------------------------------------------------------------------------

it('AC-010: returns 7 DaySummary for a Mon..Sun period, is_active true only on the day with entries', function () {
    $actor = timeEntryActorWith(['viewAny']);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-15')->create(); // Tuesday
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-20')->assertOk();

    $days = collect($response->json('items'));
    expect($days)->toHaveCount(7);

    foreach ($days as $day) {
        expect($day['is_active'])->toBe($day['date'] === '2026-09-15');
    }
});

// ---------------------------------------------------------------------------
// AC-011 — target_minutes from the profile, fallback to config, status thresholds
// ---------------------------------------------------------------------------

it('AC-011: target_minutes is 480 from a 510/30 profile, and status thresholds are exact-to-the-minute', function () {
    $actor = timeEntryActorWith(['viewAny']);
    EmploymentProfile::factory()->create(['user_id' => $actor->id, 'standard_daily_minutes' => 510, 'break_daily_minutes' => 30]);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create(['minutes' => 479]); // Monday, under
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-15')->create(['minutes' => 480]); // Tuesday, on
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-16')->create(['minutes' => 481]); // Wednesday, over
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-16')->assertOk();
    $byDate = collect($response->json('items'))->keyBy('date');

    expect($byDate['2026-09-14']['target_minutes'])->toBe(480)
        ->and($byDate['2026-09-14']['status'])->toBe('under_target')
        ->and($byDate['2026-09-15']['status'])->toBe('on_target')
        ->and($byDate['2026-09-16']['status'])->toBe('over_target');
});

it('AC-011: without a profile, target_minutes falls back to config(time_entries.default_daily_minutes) = 480', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    expect($response->json('items.0.target_minutes'))->toBe(480);
});

it('AC-011: a profile with a null standard_daily_minutes also falls back to config, same as no profile', function () {
    $actor = timeEntryActorWith(['viewAny']);
    EmploymentProfile::factory()->create(['user_id' => $actor->id, 'standard_daily_minutes' => null, 'break_daily_minutes' => 30]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    expect($response->json('items.0.target_minutes'))->toBe(480);
});

// ---------------------------------------------------------------------------
// AC-012 — non-working days: is_non_working_day, no_target regardless of entries
// ---------------------------------------------------------------------------

it('AC-012: Saturday/Sunday/Christmas/Pasquetta 2026 are non-working with no_target even with entries', function () {
    $actor = timeEntryActorWith(['viewAny']);
    // 2026-04-04/05 Sat/Sun, 04-06 Pasquetta (Lunedi' dell'Angelo).
    foreach (['2026-04-04', '2026-04-05', '2026-04-06'] as $date) {
        TimeEntry::factory()->forUser($actor)->onDate($date)->create(['minutes' => 600]);
    }
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-04-04&date_to=2026-04-06')->assertOk();
    $byDate = collect($response->json('items'))->keyBy('date');

    foreach (['2026-04-04', '2026-04-05', '2026-04-06'] as $date) {
        expect($byDate[$date]['is_non_working_day'])->toBeTrue()
            ->and($byDate[$date]['status'])->toBe('no_target');
    }
});

it('AC-012: 2026-04-04 is not flagged as a holiday, even though it is non-working', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-04-04&date_to=2026-04-04')->assertOk();

    expect($response->json('items.0.is_holiday'))->toBeFalse()
        ->and($response->json('items.0.is_non_working_day'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-013 — day-level filters: daily_statuses[] + is_active
// ---------------------------------------------------------------------------

it('AC-013: daily_statuses[]=over_target and is_active=true narrows to matching days, pagination.total coherent', function () {
    $actor = timeEntryActorWith(['viewAny']);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create(['minutes' => 500]); // over
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-15')->create(['minutes' => 100]); // under
    // 2026-09-16 stays empty (is_active false).
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-16&daily_statuses[]=over_target&is_active=true')
        ->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items'))->toHaveCount(1)
        ->and($response->json('items.0.date'))->toBe('2026-09-14');
});

// ---------------------------------------------------------------------------
// AC-014 — entry-level filters, AND across filter types, OR within one
// ---------------------------------------------------------------------------

it('AC-014: entry-level filters keep only entries matching ALL filter types (OR within each)', function () {
    $actor = timeEntryActorWith(['viewAny']);
    [$typeA, $typeB, $typeOther] = TaskType::factory()->count(3)->create();
    [$registryA, $registryB, $registryOther] = Registry::factory()->count(3)->create();

    $matchOne = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')
        ->create(['task_type_id' => $typeA->id, 'registry_id' => $registryA->id]);
    $matchTwo = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')
        ->create(['task_type_id' => $typeB->id, 'registry_id' => $registryB->id]);
    $wrongType = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')
        ->create(['task_type_id' => $typeOther->id, 'registry_id' => $registryA->id]);
    $wrongRegistry = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')
        ->create(['task_type_id' => $typeA->id, 'registry_id' => $registryOther->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson(
        '/api/time-entries?date_from=2026-09-14&date_to=2026-09-14'
        ."&task_type_ids[]={$typeA->id}&task_type_ids[]={$typeB->id}"
        ."&registry_ids[]={$registryA->id}&registry_ids[]={$registryB->id}"
    )->assertOk();

    $ids = collect($response->json('items.0.entries'))->pluck('id');

    expect($ids)->toContain($matchOne->id, $matchTwo->id)
        ->not->toContain($wrongType->id)
        ->not->toContain($wrongRegistry->id);
});

// ---------------------------------------------------------------------------
// AC-015 — sort/pagination, invalid sort_by, oversized range
// ---------------------------------------------------------------------------

it('AC-015: sort_by=target_minutes desc, per_page=2 page 2 returns the correct slice/offset', function () {
    $actor = timeEntryActorWith(['viewAny']);
    EmploymentProfile::factory()->create(['user_id' => $actor->id, 'standard_daily_minutes' => 480, 'break_daily_minutes' => 0]);
    Sanctum::actingAs($actor);

    // A 4-working-day range (Mon..Thu), all with the same target_minutes
    // (480) via the same profile — target_minutes ties resolve by the
    // date-asc tie-break (data_contract), which this test also exercises.
    $response = $this->getJson(
        '/api/time-entries?date_from=2026-09-14&date_to=2026-09-17&sort_by=target_minutes&sort_direction=desc&per_page=2&page=2'
    )->assertOk();

    expect($response->json('pagination.offset'))->toBe(2)
        ->and($response->json('pagination.limit'))->toBe(2)
        ->and($response->json('pagination.total'))->toBe(4)
        ->and(collect($response->json('items'))->pluck('date')->all())->toBe(['2026-09-16', '2026-09-17']);
});

it('AC-015: an unsupported sort_by is 422', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-14&sort_by=notes')
        ->assertStatus(422)->assertJsonValidationErrors('sort_by');
});

it('AC-015: a 367-day range is 422 on date_to', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries?date_from=2026-01-01&date_to=2027-01-03')
        ->assertStatus(422)->assertJsonValidationErrors('date_to');
});

// ---------------------------------------------------------------------------
// AC-021 (addendum) — day_note surfaces in the DaySummary
// ---------------------------------------------------------------------------

it('AC-021: a day note written via PUT day-notes appears as day_note in the matching DaySummary', function () {
    $actor = timeEntryActorWith(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/time-entries/day-notes', ['date' => '2026-09-14', 'note' => 'abc'])->assertOk();

    $response = $this->getJson('/api/time-entries?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    expect($response->json('items.0.day_note'))->toBe('abc');
});

// ---------------------------------------------------------------------------
// No N+1: one query for entries, one for notes, regardless of period length
// ---------------------------------------------------------------------------

it('runs a bounded number of queries for a 30-day period with several entries (no N+1 per day)', function () {
    $actor = timeEntryActorWith(['viewAny']);
    foreach (range(0, 5) as $offset) {
        TimeEntry::factory()->forUser($actor)->onDate(now()->parse('2026-09-01')->addDays($offset)->format('Y-m-d'))->create();
    }
    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->getJson('/api/time-entries?date_from=2026-09-01&date_to=2026-09-30')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Entries + notes + the actor's own employment/profile lookups + the
    // subordinate-resolver projection for meta.team — all fixed-count,
    // independent of the 30 days iterated in PHP.
    expect($queryCount)->toBeLessThanOrEqual(10);
});
