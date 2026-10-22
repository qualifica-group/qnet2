<?php

use App\Models\CompanyClosure;
use App\Models\OperationalSite;
use App\Models\User;
use App\Services\Absences\Calendar\PersonNonWorkingDays;
use App\Services\Absences\Calendar\PersonWorkCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function calendarPersonAt(?OperationalSite $site): User
{
    $person = User::factory()->create();
    $profile = absenceProfile($person);

    if ($site !== null) {
        $profile->operationalSites()->attach($site->id, ['is_primary' => true]);
    }

    return $person;
}

it('0304 B2: an all-sites closure is non-working for everyone, a site closure only for that site', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $inA = calendarPersonAt($siteA);
    $inB = calendarPersonAt($siteB);
    $noSite = calendarPersonAt(null);

    CompanyClosure::factory()->create(['name' => 'Bridge', 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']);
    $patron = CompanyClosure::factory()->create(['name' => 'Patron', 'starts_on' => '2026-12-15', 'ends_on' => '2026-12-15', 'applies_to_all_sites' => false]);
    $patron->operationalSites()->attach($siteA->id);

    $days = app(PersonWorkCalendar::class)->load([$inA->id, $inB->id, $noSite->id], '2026-12-14', '2026-12-18');

    expect($days->reason($inA->id, '2026-12-14'))->toBe(['reason' => 'closure', 'name' => 'Bridge'])
        ->and($days->isNonWorkingDay($inA->id, '2026-12-15'))->toBeTrue()
        ->and($days->isNonWorkingDay($inB->id, '2026-12-15'))->toBeFalse()
        ->and($days->isNonWorkingDay($noSite->id, '2026-12-15'))->toBeFalse()
        ->and($days->isNonWorkingDay($noSite->id, '2026-12-14'))->toBeTrue()
        ->and($days->closedDays($inA->id))->toBe([
            ['date' => '2026-12-14', 'name' => 'Bridge'],
            ['date' => '2026-12-15', 'name' => 'Patron'],
        ])
        ->and($days->closedDays($inB->id))->toBe([['date' => '2026-12-14', 'name' => 'Bridge']]);
});

it('0304 B2: weekend and national holiday win over a closure and keep their own reason', function () {
    $person = calendarPersonAt(null);
    CompanyClosure::factory()->create(['starts_on' => '2026-12-05', 'ends_on' => '2026-12-08']);

    $days = app(PersonWorkCalendar::class)->load([$person->id], '2026-12-01', '2026-12-31');

    expect($days->reason($person->id, '2026-12-05')['reason'])->toBe(PersonNonWorkingDays::REASON_WEEKEND)
        ->and($days->reason($person->id, '2026-12-08')['reason'])->toBe(PersonNonWorkingDays::REASON_HOLIDAY)
        ->and($days->reason($person->id, '2026-12-07')['reason'])->toBe(PersonNonWorkingDays::REASON_CLOSURE)
        ->and($days->reason($person->id, '2026-12-09'))->toBeNull();
});

it('0304 B2: a yearly closure repeats from its first year on and not before', function () {
    $person = calendarPersonAt(null);
    CompanyClosure::factory()->yearly()->create(['name' => 'Patron', 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-07']);

    $calendar = app(PersonWorkCalendar::class);

    expect($calendar->load([$person->id], '2027-12-06', '2027-12-08')->closureName($person->id, '2027-12-07'))->toBe('Patron')
        ->and($calendar->load([$person->id], '2025-12-06', '2025-12-08')->closureName($person->id, '2025-12-07'))->toBeNull();
});

it('0304 B2: collective leave closures do not make a day non-working', function () {
    $person = calendarPersonAt(null);
    CompanyClosure::factory()->collectiveLeave()->create(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']);

    expect(app(PersonWorkCalendar::class)->load([$person->id], '2026-12-14', '2026-12-14')->isNonWorkingDay($person->id, '2026-12-14'))->toBeFalse();
});

it('0304 B2: loading costs the same number of queries whatever the number of people', function () {
    $site = OperationalSite::factory()->create();
    CompanyClosure::factory()->create(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-14', 'applies_to_all_sites' => false])
        ->operationalSites()->attach($site->id);
    $few = collect(range(1, 2))->map(fn () => calendarPersonAt($site)->id)->all();
    $many = [...$few, ...collect(range(1, 6))->map(fn () => calendarPersonAt($site)->id)->all()];

    $count = function (array $ids): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PersonWorkCalendar::class)->load($ids, '2026-12-01', '2026-12-31');

        return count(DB::getQueryLog());
    };

    expect($count($many))->toBe($count($few));
});
