<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0304 D-14 — a holiday closure is a non-working day for the people it applies to.
uses(RefreshDatabase::class);

function closureTimeEntryPerson(OperationalSite $site): User
{
    $person = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $person->id, 'standard_daily_minutes' => 480, 'break_daily_minutes' => 0])
        ->operationalSites()->attach($site->id, ['is_primary' => true]);

    return $person;
}

/** Wed 2026-09-16 closes site A; the period is Mon 14 .. Fri 18. */
function closureScenario(): array
{
    foreach (['viewAny', 'viewAll', 'manageAll', 'export'] as $ability) {
        Permission::findOrCreate("time-entries.{$ability}");
    }

    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $inA = closureTimeEntryPerson($siteA);
    $inB = closureTimeEntryPerson($siteB);
    CompanyClosure::factory()->create(['name' => 'Bridge', 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-16', 'applies_to_all_sites' => false])
        ->operationalSites()->attach($siteA->id);

    $actor = User::factory()->create();
    $actor->givePermissionTo('time-entries.viewAny', 'time-entries.viewAll', 'time-entries.manageAll', 'time-entries.export');
    Sanctum::actingAs($actor);

    return [$inA, $inB];
}

function closureDay(User $person, string $date): array
{
    return collect(test()->getJson("/api/time-entries?date_from=2026-09-14&date_to=2026-09-18&user_id={$person->id}")->assertOk()->json('items'))
        ->firstWhere('date', $date);
}

it('AC-017: a closure day has target 0, no_target and the closure name; another site is unchanged', function () {
    [$inA, $inB] = closureScenario();

    $dayA = closureDay($inA, '2026-09-16');
    $dayB = closureDay($inB, '2026-09-16');

    expect($dayA['target_minutes'])->toBe(0)
        ->and($dayA['status'])->toBe('no_target')
        ->and($dayA['closure'])->toBe(['name' => 'Bridge'])
        ->and($dayA['is_holiday'])->toBeFalse()
        ->and($dayB['target_minutes'])->toBe(480)
        ->and($dayB['closure'])->toBeNull()
        ->and(closureDay($inA, '2026-09-15')['closure'])->toBeNull();
});

it('AC-017: stats overview skips the closure day of the person', function () {
    [$inA, $inB] = closureScenario();

    $overview = fn (User $person) => $this->getJson("/api/time-entries/stats/overview?date_from=2026-09-14&date_to=2026-09-18&user_id={$person->id}")->assertOk()->json('data');

    expect($overview($inA)['working_days'])->toBe(4)
        ->and($overview($inA)['period_target_minutes'])->toBe(4 * 480)
        ->and($overview($inB)['working_days'])->toBe(5);
});

it('AC-017: the team pulse counts the working days of each member', function () {
    [$inA, $inB] = closureScenario();

    $items = collect($this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-18')->assertOk()->json('data.items'))->keyBy('user.id');

    expect($items[$inA->id]['coverage']['working_days'])->toBe(4)
        ->and($items[$inB->id]['coverage']['working_days'])->toBe(5);
});

it('AC-017: a full-day absence spreads over the working days of the person, closure excluded', function () {
    [$inA] = closureScenario();
    AbsenceRequest::factory()->forUser($inA)->approved()->create([
        'absence_type_id' => AbsenceType::factory()->create(['name' => 'Ferie'])->id,
        'starts_on' => '2026-09-15', 'ends_on' => '2026-09-17', 'is_full_day' => true,
    ]);

    expect(closureDay($inA, '2026-09-15')['absences'])->toHaveCount(1)
        ->and(closureDay($inA, '2026-09-16')['absences'])->toBe([])
        ->and(closureDay($inA, '2026-09-17')['absences'])->toHaveCount(1);
});

it('AC-017: the filtered export keeps the entries of a closure day', function () {
    [$inA] = closureScenario();
    TimeEntry::factory()->forUser($inA)->onDate('2026-09-16')->create(['minutes' => 60]);

    $this->get("/api/time-entries/exports/filtered?date_from=2026-09-14&date_to=2026-09-18&user_id={$inA->id}")->assertOk();
});
