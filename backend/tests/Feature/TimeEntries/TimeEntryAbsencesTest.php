<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\EmploymentProfile;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Approved absences lower the segnatempo target (spec 0278, D-3, AC-016)
|--------------------------------------------------------------------------
| Mon 2026-09-14 .. Fri 2026-09-18, base target 480 (config default, no profile).
*/

function absenceTimeEntryActor(array $abilities = ['viewAny']): User
{
    foreach (['viewAny', 'viewAll'] as $ability) {
        Permission::findOrCreate("time-entries.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("time-entries.{$ability}");
    }

    return $user;
}

function absenceOnDays(User $user, array $attributes, string $state = 'approved'): AbsenceRequest
{
    $factory = AbsenceRequest::factory()->forUser($user);

    return ($state === 'draft' ? $factory : $factory->{$state}())->create($attributes + [
        'is_full_day' => true,
        'starts_on' => '2026-09-14',
        'ends_on' => '2026-09-14',
    ]);
}

/**
 * @return array<string, mixed>
 */
function absenceDay(string $date, int $userId): array
{
    $response = test()->getJson("/api/time-entries?date_from=2026-09-14&date_to=2026-09-18&user_id={$userId}")->assertOk();

    return collect($response->json('items'))->firstWhere('date', $date);
}

it('AC-016: an approved full-day absence zeroes the target and the status is no_target', function () {
    $actor = absenceTimeEntryActor();
    $type = AbsenceType::factory()->create(['name' => 'Ferie']);
    $request = absenceOnDays($actor, ['absence_type_id' => $type->id, 'starts_on' => '2026-09-15', 'ends_on' => '2026-09-16']);

    Sanctum::actingAs($actor);

    $tuesday = absenceDay('2026-09-15', $actor->id);
    expect($tuesday['target_minutes'])->toBe(0)
        ->and($tuesday['status'])->toBe('no_target')
        ->and($tuesday['absences'])->toBe([[
            'id' => $request->id,
            'absence_type_name' => 'Ferie',
            'is_full_day' => true,
            'minutes' => 480,
        ]])
        ->and(absenceDay('2026-09-16', $actor->id)['target_minutes'])->toBe(0)
        ->and(absenceDay('2026-09-17', $actor->id)['target_minutes'])->toBe(480)
        ->and(absenceDay('2026-09-17', $actor->id)['absences'])->toBe([]);
});

it('AC-016: a full-day range skips weekends and holidays', function () {
    $actor = absenceTimeEntryActor();
    absenceOnDays($actor, ['starts_on' => '2026-09-18', 'ends_on' => '2026-09-21']);

    Sanctum::actingAs($actor);
    $response = $this->getJson("/api/time-entries?date_from=2026-09-18&date_to=2026-09-21&user_id={$actor->id}")->assertOk();
    $days = collect($response->json('items'))->keyBy('date');

    expect($days['2026-09-18']['absences'])->toHaveCount(1)
        ->and($days['2026-09-19']['absences'])->toBe([])
        ->and($days['2026-09-20']['absences'])->toBe([])
        ->and($days['2026-09-21']['absences'])->toHaveCount(1);
});

it('AC-016: an hourly absence lowers the target by its minutes on starts_on only', function () {
    $actor = absenceTimeEntryActor();
    absenceOnDays($actor, ['is_full_day' => false, 'requested_minutes' => 120, 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-17'], 'approved');
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-16')->create(['minutes' => 360]);

    Sanctum::actingAs($actor);

    $wednesday = absenceDay('2026-09-16', $actor->id);
    expect($wednesday['target_minutes'])->toBe(360)
        ->and($wednesday['status'])->toBe('on_target')
        ->and($wednesday['absences'][0]['minutes'])->toBe(120)
        ->and($wednesday['absences'][0]['is_full_day'])->toBeFalse()
        ->and(absenceDay('2026-09-17', $actor->id)['target_minutes'])->toBe(480);
});

it('AC-016: draft, submitted, rejected and cancelled absences change nothing', function () {
    $actor = absenceTimeEntryActor();
    absenceOnDays($actor, ['starts_on' => '2026-09-15', 'ends_on' => '2026-09-15'], 'submitted');
    absenceOnDays($actor, ['starts_on' => '2026-09-16', 'ends_on' => '2026-09-16'], 'draft');
    absenceOnDays($actor, ['starts_on' => '2026-09-17', 'ends_on' => '2026-09-17', 'status' => 'rejected']);
    absenceOnDays($actor, ['starts_on' => '2026-09-18', 'ends_on' => '2026-09-18', 'status' => 'cancelled']);

    Sanctum::actingAs($actor);

    foreach (['2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18'] as $date) {
        $day = absenceDay($date, $actor->id);
        expect($day['target_minutes'])->toBe(480)->and($day['absences'])->toBe([]);
    }
});

it('AC-016: the absence lookup is a single query regardless of the period length', function () {
    $actor = absenceTimeEntryActor();
    absenceOnDays($actor, ['starts_on' => '2026-09-14', 'ends_on' => '2026-09-18']);
    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->getJson("/api/time-entries?date_from=2026-09-14&date_to=2026-09-18&user_id={$actor->id}")->assertOk();
    $absenceQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'absence_requests'));

    expect($absenceQueries)->toHaveCount(1);
});

it('AC-016: overview period target uses the reduced daily targets', function () {
    $actor = absenceTimeEntryActor();
    absenceOnDays($actor, ['starts_on' => '2026-09-15', 'ends_on' => '2026-09-15']);
    absenceOnDays($actor, ['is_full_day' => false, 'requested_minutes' => 120, 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-16']);

    Sanctum::actingAs($actor);
    $response = $this->getJson('/api/time-entries/stats/overview?date_from=2026-09-14&date_to=2026-09-18')->assertOk();

    expect($response->json('data.period_target_minutes'))->toBe(480 + 0 + 360 + 480 + 480)
        ->and($response->json('data.daily_target_minutes'))->toBe(480);
});

it('AC-016: team pulse coverage uses the same reduced target as the personal pulse', function () {
    $manager = absenceTimeEntryActor(['viewAny']);
    $member = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $manager->id]);
    EmploymentProfile::factory()->reportsTo($manager)->create([
        'user_id' => $member->id,
        'standard_daily_minutes' => 480,
        'break_daily_minutes' => 0,
    ]);
    absenceOnDays($member, ['starts_on' => '2026-09-15', 'ends_on' => '2026-09-15']);
    absenceOnDays($member, ['is_full_day' => false, 'requested_minutes' => 240, 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-16']);
    TimeEntry::factory()->forUser($member)->onDate('2026-09-14')->create(['minutes' => 480]);
    TimeEntry::factory()->forUser($member)->onDate('2026-09-16')->create(['minutes' => 240]);

    Sanctum::actingAs($manager);
    $team = $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-16')->assertOk();

    // target = 480 + 0 + 240 = 720, tracked = 720
    expect($team->json('data.items.0.coverage.percentage'))->toBe(100);

    $pulse = $this->getJson("/api/time-entries/stats/pulse?date_from=2026-09-14&date_to=2026-09-16&user_id={$member->id}")->assertOk();

    expect($pulse->json('data.coverage.percentage'))->toBe(100);
});
