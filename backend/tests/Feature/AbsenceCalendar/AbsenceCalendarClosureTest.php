<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0304 D-11/D-14 — calendar: type color, day part, closed days per person.
uses(RefreshDatabase::class);

function closureCalendarPerson(OperationalSite $site, string $name): User
{
    $person = User::factory()->create(['name' => $name]);
    absenceProfile($person)->operationalSites()->attach($site->id, ['is_primary' => true]);

    return $person;
}

function closureCalendarRequest(string $from, string $to): string
{
    return "/api/absences/calendar?from={$from}&to={$to}";
}

it('AC-015: closed_days only for the person of the site the closure applies to, and it repeats the year after', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $inA = closureCalendarPerson($siteA, 'Anna');
    $inB = closureCalendarPerson($siteB, 'Bruno');
    $patron = CompanyClosure::factory()->yearly()->create([
        'name' => 'Patron saint', 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-07', 'applies_to_all_sites' => false,
    ]);
    $patron->operationalSites()->attach($siteA->id);

    $actor = grantAbsencePermissions(User::factory()->create(), ['absence-requests.calendarAll']);
    Sanctum::actingAs($actor);

    $people = collect($this->getJson(closureCalendarRequest('2026-12-01', '2026-12-31'))->assertOk()->json('data.people'))->keyBy('id');
    $nextYear = collect($this->getJson(closureCalendarRequest('2027-12-01', '2027-12-31'))->assertOk()->json('data.people'))->keyBy('id');

    expect($people[$inA->id]['closed_days'])->toBe([['date' => '2026-12-07', 'name' => 'Patron saint']])
        ->and($people[$inB->id]['closed_days'])->toBe([])
        ->and($nextYear[$inA->id]['closed_days'])->toBe([['date' => '2027-12-07', 'name' => 'Patron saint']])
        ->and($nextYear[$inB->id]['closed_days'])->toBe([]);
});

it('AC-015: a multi-day absence skips the closure days of the person only', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $inA = closureCalendarPerson($siteA, 'Anna');
    $inB = closureCalendarPerson($siteB, 'Bruno');
    $closure = CompanyClosure::factory()->create(['name' => 'Bridge', 'starts_on' => '2026-12-16', 'ends_on' => '2026-12-16', 'applies_to_all_sites' => false]);
    $closure->operationalSites()->attach($siteA->id);
    $type = AbsenceType::factory()->create(['name' => 'Ferie', 'color' => '#112233']);

    foreach ([$inA, $inB] as $person) {
        AbsenceRequest::factory()->forUser($person)->approved()->create([
            'absence_type_id' => $type->id, 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-18',
        ]);
    }

    Sanctum::actingAs(grantAbsencePermissions(User::factory()->create(), ['absence-requests.calendarAll']));
    $people = collect($this->getJson(closureCalendarRequest('2026-12-14', '2026-12-18'))->assertOk()->json('data.people'))->keyBy('id');

    expect(collect($people[$inA->id]['absences'])->pluck('date')->all())->toBe(['2026-12-14', '2026-12-15', '2026-12-17', '2026-12-18'])
        ->and(collect($people[$inB->id]['absences'])->pluck('date')->all())->toHaveCount(5)
        ->and($people[$inA->id]['absences'][0]['color'])->toBe('#112233');
});

it('exposes the day part of a half-day absence', function () {
    $site = OperationalSite::factory()->create();
    $person = closureCalendarPerson($site, 'Anna');
    AbsenceRequest::factory()->forUser($person)->approved()->create([
        'absence_type_id' => AbsenceType::factory()->create()->id,
        'starts_on' => '2026-12-14', 'ends_on' => '2026-12-14',
        'is_full_day' => false, 'day_part' => 'morning', 'requested_minutes' => 240,
    ]);

    Sanctum::actingAs(grantAbsencePermissions(User::factory()->create(), ['absence-requests.calendarAll']));
    $entry = collect($this->getJson(closureCalendarRequest('2026-12-14', '2026-12-14'))->assertOk()->json('data.people'))
        ->firstWhere('id', $person->id)['absences'][0];

    expect($entry['day_part'])->toBe('morning')->and($entry['is_full_day'])->toBeFalse();
});
