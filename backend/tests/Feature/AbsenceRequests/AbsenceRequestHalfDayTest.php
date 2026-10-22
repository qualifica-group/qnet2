<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

function halfDayPerson(array $profile = []): User
{
    $person = absenceUserWith();
    absenceProfile($person, absenceUserWith(), $profile);

    return $person;
}

it('0304 AC-002: a morning half day on a working day stores 240 minutes and freezes minutes 240 on submit', function () {
    Sanctum::actingAs(halfDayPerson());

    $id = $this->postJson('/api/absence-requests', absencePayload(['is_full_day' => false, 'day_part' => 'morning', 'requested_minutes' => 999]))
        ->assertCreated()
        ->assertJsonPath('data.day_part', 'morning')
        ->assertJsonPath('data.requested_minutes', 240)
        ->assertJsonPath('data.is_full_day', false)
        ->json('data.id');

    $this->postJson("/api/absence-requests/{$id}/submit")
        ->assertOk()
        ->assertJsonPath('data.minutes', 240)
        ->assertJsonPath('data.requested_minutes', 240);
});

it('0304 AC-002: the half day follows the daily hours of the person', function () {
    Sanctum::actingAs(halfDayPerson(['standard_daily_minutes' => 360]));

    $this->postJson('/api/absence-requests', absencePayload(['is_full_day' => false, 'day_part' => 'afternoon', 'submit' => true]))
        ->assertCreated()->assertJsonPath('data.minutes', 180)->assertJsonPath('data.daily_minutes', 360);
});

it('0304 AC-002: a half day on a weekend, with another end date or with a full day is 422', function () {
    Sanctum::actingAs(halfDayPerson());
    $half = ['is_full_day' => false, 'day_part' => 'morning'];

    $this->postJson('/api/absence-requests', absencePayload([...$half, 'starts_on' => '2026-12-12', 'ends_on' => '2026-12-12']))
        ->assertStatus(422)->assertJsonValidationErrors('starts_on');
    $this->postJson('/api/absence-requests', absencePayload([...$half, 'ends_on' => '2026-12-15']))
        ->assertStatus(422)->assertJsonValidationErrors('ends_on');
    $this->postJson('/api/absence-requests', absencePayload(['is_full_day' => true, 'day_part' => 'morning']))
        ->assertStatus(422)->assertJsonValidationErrors('day_part');
    $this->postJson('/api/absence-requests', absencePayload([...$half, 'day_part' => 'evening']))
        ->assertStatus(422)->assertJsonValidationErrors('day_part');
});

it('0304 AC-002: a mode the type does not allow is 422 on is_full_day', function () {
    Sanctum::actingAs(halfDayPerson());
    $noHalf = AbsenceType::factory()->create(['allows_half_day' => false])->id;
    $noFull = AbsenceType::factory()->create(['allows_full_day' => false])->id;
    $noHourly = AbsenceType::factory()->create(['allows_hourly' => false])->id;

    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $noHalf, 'is_full_day' => false, 'day_part' => 'morning']))
        ->assertStatus(422)->assertJsonValidationErrors('is_full_day');
    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $noFull]))
        ->assertStatus(422)->assertJsonValidationErrors('is_full_day');
    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $noHourly, 'is_full_day' => false, 'requested_minutes' => 60]))
        ->assertStatus(422)->assertJsonValidationErrors('is_full_day');
    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $noHalf, 'is_full_day' => false, 'requested_minutes' => 60]))
        ->assertCreated();
});

it('0304 AC-002: patching a draft keeps the half day coherent', function () {
    Sanctum::actingAs(halfDayPerson());
    $payload = absencePayload(['is_full_day' => false, 'day_part' => 'morning']);
    $id = $this->postJson('/api/absence-requests', $payload)->assertCreated()->json('data.id');

    $this->patchJson("/api/absence-requests/{$id}", [...$payload, 'day_part' => 'afternoon'])
        ->assertOk()->assertJsonPath('data.day_part', 'afternoon')->assertJsonPath('data.requested_minutes', 240);
    $this->patchJson("/api/absence-requests/{$id}", [...$payload, 'is_full_day' => true, 'day_part' => null])
        ->assertOk()->assertJsonPath('data.day_part', null)->assertJsonPath('data.requested_minutes', null);
});

it('0304 AC-003: short notice is fixed at submission from the notice of the type', function (string $startsOn, bool $expected) {
    Sanctum::actingAs(halfDayPerson());
    $type = AbsenceType::factory()->create(['min_notice_days' => 7]);

    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $type->id, 'starts_on' => $startsOn, 'ends_on' => $startsOn, 'submit' => true]))
        ->assertCreated()->assertJsonPath('data.is_short_notice', $expected);
})->with([
    'three days ahead' => ['2026-10-26', true],
    'eleven days ahead' => ['2026-11-02', false],
    'exactly seven days ahead' => ['2026-10-29', false],
]);

it('0304 AC-003: a type without notice is never short, a draft is not short yet', function () {
    Sanctum::actingAs(halfDayPerson());
    $noNotice = AbsenceType::factory()->create(['min_notice_days' => null]);
    $notice = AbsenceType::factory()->create(['min_notice_days' => 7]);

    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $noNotice->id, 'starts_on' => '2026-10-23', 'ends_on' => '2026-10-23', 'submit' => true]))
        ->assertCreated()->assertJsonPath('data.is_short_notice', false);
    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $notice->id, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26']))
        ->assertCreated()->assertJsonPath('data.is_short_notice', false);
});

it('0304 AC-003: entered by the HR office for someone else it is never short, and it does not change with the type', function () {
    $hr = absenceUserWith(['viewAny', 'create', 'manageAll']);
    $person = halfDayPerson();
    $type = AbsenceType::factory()->create(['min_notice_days' => 30]);
    Sanctum::actingAs($hr);

    $id = $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $type->id, 'user_id' => $person->id, 'starts_on' => '2026-10-23', 'ends_on' => '2026-10-23', 'submit' => true]))
        ->assertCreated()->assertJsonPath('data.is_short_notice', false)->json('data.id');

    Sanctum::actingAs($person);
    $own = $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $type->id, 'starts_on' => '2026-10-27', 'ends_on' => '2026-10-27', 'submit' => true]))
        ->assertCreated()->assertJsonPath('data.is_short_notice', true)->json('data.id');

    $type->update(['min_notice_days' => null]);

    expect(AbsenceRequest::find($own)->is_short_notice)->toBeTrue()
        ->and(AbsenceRequest::find($id)->is_short_notice)->toBeFalse();
});

it('0304 AC-015: a holiday closure of the site is excluded from the duration of that person only', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $inA = halfDayPerson();
    $inB = halfDayPerson();
    absenceProfile($inA)->operationalSites()->attach($siteA->id, ['is_primary' => true]);
    absenceProfile($inB)->operationalSites()->attach($siteB->id, ['is_primary' => true]);
    CompanyClosure::factory()->create(['name' => 'Bridge', 'starts_on' => '2026-12-15', 'ends_on' => '2026-12-15', 'applies_to_all_sites' => false])
        ->operationalSites()->attach($siteA->id);
    $type = AbsenceType::factory()->create()->id;
    $week = ['absence_type_id' => $type, 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-18', 'submit' => true];

    Sanctum::actingAs($inA);
    $this->postJson('/api/absence-requests', absencePayload($week))->assertCreated()->assertJsonPath('data.minutes', 4 * 480);
    Sanctum::actingAs($inB);
    $this->postJson('/api/absence-requests', absencePayload($week))->assertCreated()->assertJsonPath('data.minutes', 5 * 480);
});

it('0304 AC-015: a request that only covers a closure day has no working day', function () {
    $person = halfDayPerson();
    CompanyClosure::factory()->create(['starts_on' => '2026-12-15', 'ends_on' => '2026-12-15']);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['starts_on' => '2026-12-15', 'ends_on' => '2026-12-15']))
        ->assertStatus(422)->assertJsonValidationErrors('starts_on');
});

it('0304 AC-008: the resource carries day_part, notice, closure, daily minutes and the type color', function () {
    $person = halfDayPerson(['standard_daily_minutes' => 420, 'break_daily_minutes' => 60]);
    $closure = CompanyClosure::factory()->collectiveLeave()->create(['name' => 'August']);
    $type = AbsenceType::factory()->create(['color' => 'amber']);
    $request = AbsenceRequest::factory()->forUser($person)->approved()->create([
        'absence_type_id' => $type->id, 'company_closure_id' => $closure->id, 'is_short_notice' => true,
    ]);
    Sanctum::actingAs($person);

    $this->getJson("/api/absence-requests/{$request->id}")->assertOk()
        ->assertJsonPath('data.day_part', null)
        ->assertJsonPath('data.is_short_notice', true)
        ->assertJsonPath('data.company_closure', ['id' => $closure->id, 'name' => 'August'])
        ->assertJsonPath('data.daily_minutes', 360)
        ->assertJsonPath('data.absence_type.color', 'amber');
});

it('0304 D-15: the person cannot cancel a closure-generated request, the HR office can', function () {
    $person = halfDayPerson();
    $hr = absenceUserWith(['viewAny', 'create', 'manageAll']);
    $closure = CompanyClosure::factory()->collectiveLeave()->create();
    $request = AbsenceRequest::factory()->forUser($person)->approved()->create(['company_closure_id' => $closure->id, 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']);

    Sanctum::actingAs($person);
    $this->getJson("/api/absence-requests/{$request->id}")->assertJsonPath('data.can.cancel', false);
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertForbidden();

    Sanctum::actingAs($hr);
    $this->getJson("/api/absence-requests/{$request->id}")->assertJsonPath('data.can.cancel', true);
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk();
    expect($request->fresh()->status)->toBe(AbsenceRequestStatus::Cancelled);
});

it('0304 AC-008: the "to approve" rows carry the fields and the approve/reject actions only for who can decide', function () {
    $approver = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $approver, ['standard_daily_minutes' => 420, 'break_daily_minutes' => 60]);
    $request = absenceSubmitted($person, $approver, ['is_full_day' => false, 'day_part' => 'afternoon', 'requested_minutes' => 180, 'is_short_notice' => true]);

    Sanctum::actingAs($approver);
    $row = $this->postJson('/api/tables/absence-requests-to-approve/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');
    $columns = collect($this->getJson('/api/tables/absence-requests-to-approve/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $actions = collect($this->getJson('/api/tables/absence-requests-to-approve/columns')->json('data.actions'))->pluck('key')->all();

    expect($row)->toMatchArray(['id' => $request->id, 'day_part' => 'afternoon', 'is_short_notice' => true, 'daily_minutes' => 360])
        ->and($row['actions'])->toContain('view', 'approve', 'reject')
        ->and($columns)->toContain('day_part', 'is_short_notice')
        ->and($actions)->toContain('approve', 'reject');
});

it('0304 AC-008: the "mine" grid has no decision actions and the owner cannot decide', function () {
    $person = absenceUserWith();
    absenceSubmitted($person, absenceUserWith());
    Sanctum::actingAs($person);

    $row = $this->postJson('/api/tables/absence-requests/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($row['actions'])->toBe(['view']);
});
