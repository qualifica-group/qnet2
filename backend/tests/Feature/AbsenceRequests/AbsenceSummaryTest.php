<?php

use App\Models\CompanyClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('0304 AC-009: the summary counts the same rows as the "to approve" grid and ordering of upcoming', function () {
    $actor = absenceUserWith();
    foreach (range(1, 3) as $ignored) {
        absenceSubmitted(absenceUserWith(), $actor);
    }
    absenceSubmitted(absenceUserWith(), absenceUserWith());
    $starts = ['2026-11-20', '2026-11-03', '2026-12-01', '2026-11-10', '2026-11-25', '2026-11-30'];
    foreach ($starts as $start) {
        insightAbsence($actor, 'approved', ['starts_on' => $start, 'ends_on' => $start]);
    }
    insightAbsence($actor, 'approved', ['starts_on' => '2026-09-01', 'ends_on' => '2026-09-01']);
    insightAbsence($actor, 'submitted', ['starts_on' => '2026-10-23', 'ends_on' => '2026-10-23']);
    Sanctum::actingAs($actor);

    $gridTotal = count($this->postJson('/api/tables/absence-requests-to-approve/rows', ['startRow' => 0, 'endRow' => 50])->json('items'));
    $summary = $this->getJson('/api/absences/summary')->assertOk()->json('data');

    expect($summary['to_approve_count'])->toBe(3)->toBe($gridTotal)
        ->and($summary['my_pending_count'])->toBe(1)
        ->and($summary['daily_minutes'])->toBe(480)
        ->and(collect($summary['my_upcoming'])->pluck('starts_on')->all())->toBe(['2026-10-23', '2026-11-03', '2026-11-10', '2026-11-20', '2026-11-25'])
        ->and($summary['my_upcoming'][0])->toHaveKeys(['request_id', 'absence_type' => ['id', 'name', 'color'], 'status', 'ends_on', 'is_full_day', 'day_part', 'minutes']);
});

it('0304 AC-009: absent today shows approved absences of the visible people on a working day only', function () {
    Carbon::setTestNow('2026-10-22 09:00:00');
    $actor = grantAbsencePermissions(absenceUserWith(), ['absence-requests.calendarAll']);
    $away = insightPerson();
    $pending = insightPerson();
    $future = insightPerson();
    insightAbsence($away, 'approved', ['starts_on' => '2026-10-21', 'ends_on' => '2026-10-23', 'is_full_day' => false, 'day_part' => 'morning']);
    insightAbsence($pending, 'submitted', ['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
    insightAbsence($future, 'approved', ['starts_on' => '2026-10-23', 'ends_on' => '2026-10-23']);
    Sanctum::actingAs($actor);

    $absent = $this->getJson('/api/absences/summary')->assertOk()->json('data.absent_today');

    expect(collect($absent)->pluck('user.id')->all())->toBe([$away->id])
        ->and($absent[0])->toMatchArray(['day_part' => 'morning', 'is_full_day' => false, 'ends_on' => '2026-10-23'])
        ->and($absent[0])->toHaveKeys(['request_id', 'absence_type_name', 'color']);
});

it('0304 AC-009: absent today is empty on a closure day, without calendar permission, and only self with calendarOwn', function () {
    $away = insightPerson();
    insightAbsence($away, 'approved', ['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);

    Sanctum::actingAs(absenceUserWith());
    $this->getJson('/api/absences/summary')->assertOk()->assertJsonPath('data.absent_today', []);

    Sanctum::actingAs(grantAbsencePermissions(absenceUserWith(), ['absence-requests.calendarOwn']));
    $this->getJson('/api/absences/summary')->assertOk()->assertJsonPath('data.absent_today', []);

    Sanctum::actingAs(grantAbsencePermissions($away, ['absence-requests.calendarOwn']));
    $this->getJson('/api/absences/summary')->assertOk()->assertJsonPath('data.absent_today.0.user.id', $away->id);

    CompanyClosure::factory()->create(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
    Sanctum::actingAs(grantAbsencePermissions(absenceUserWith(), ['absence-requests.calendarAll']));
    $this->getJson('/api/absences/summary')->assertOk()->assertJsonPath('data.absent_today', []);
});

it('0304 AC-009: the summary is 403 without viewAny and 401 unauthenticated', function () {
    $this->getJson('/api/absences/summary')->assertUnauthorized();

    Sanctum::actingAs(absenceUserWith([]));
    $this->getJson('/api/absences/summary')->assertForbidden();
});
