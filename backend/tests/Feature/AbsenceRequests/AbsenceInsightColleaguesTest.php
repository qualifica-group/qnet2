<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('0304 AC-005: a colleague of the same function appears for calendarAll, never the person themselves', function () {
    $person = insightPerson();
    $colleague = insightPerson();
    insightFunction($person, $colleague);
    $absence = insightAbsence($colleague, 'approved');
    insightAbsence($person, 'approved', ['starts_on' => '2026-12-17', 'ends_on' => '2026-12-17']);
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.calendarAll']));

    $this->postJson('/api/absence-requests/preview', absencePayload(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-18']))
        ->assertOk()
        ->assertJsonCount(1, 'data.colleagues_absent')
        ->assertJsonPath('data.colleagues_absent.0.user.id', $colleague->id)
        ->assertJsonPath('data.colleagues_absent.0.request_id', $absence->id)
        ->assertJsonPath('data.colleagues_absent.0.status', 'approved')
        ->assertJsonPath('data.colleagues_absent.0.color', 'blue')
        ->assertJsonPath('data.colleagues_absent.0.is_full_day', true)
        ->assertJsonPath('data.colleagues_absent.0.day_part', null);
});

it('0304 AC-005: a colleague sharing only a direct manager counts, a stranger does not', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $sibling = absenceUserWith();
    $stranger = absenceUserWith();
    absenceProfile($person, $manager);
    absenceProfile($sibling, $manager);
    absenceProfile($stranger, absenceUserWith());
    insightAbsence($sibling, 'submitted');
    insightAbsence($stranger, 'approved');
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.calendarAll']));

    $response = $this->postJson('/api/absence-requests/preview', absencePayload())->assertOk();

    expect(collect($response->json('data.colleagues_absent'))->pluck('user.id')->all())->toBe([$sibling->id])
        ->and($response->json('data.colleagues_absent.0.status'))->toBe('submitted');
});

// REQUIREMENT CHANGED (spec 0309 D-4): the team part derives from the org chart, not from calendarTeam.
it('0304 AC-005: calendarOwn without subordinates sees nobody, with them only the descendants, no calendar permission nobody', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $team = absenceUserWith();
    $outsider = absenceUserWith();
    absenceProfile($person, absenceUserWith());
    absenceProfile($team, $manager);
    absenceProfile($outsider, absenceUserWith());
    insightFunction($person, $team, $outsider);
    insightAbsence($team, 'approved');
    insightAbsence($outsider, 'approved');
    $payload = absencePayload();

    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.calendarOwn']));
    $this->postJson('/api/absence-requests/preview', $payload)->assertOk()->assertJsonCount(0, 'data.colleagues_absent');

    Sanctum::actingAs($person->fresh());
    $person->revokePermissionTo('absence-requests.calendarOwn');
    $this->postJson('/api/absence-requests/preview', $payload)->assertOk()->assertJsonCount(0, 'data.colleagues_absent');

    $hr = grantAbsencePermissions(absenceUserWith(['viewAny', 'create', 'manageAll']), ['absence-requests.calendarOwn']);
    absenceProfile($team, $hr);
    DB::table('employment_profile_manager')->where('user_id', $manager->id)->delete();
    Sanctum::actingAs($hr);
    $response = $this->postJson('/api/absence-requests/preview', [...$payload, 'user_id' => $person->id])->assertOk();

    expect(collect($response->json('data.colleagues_absent'))->pluck('user.id')->all())->toBe([$team->id]);
});

it('0304 AC-005: rejected, cancelled and draft absences and inactive people are left out', function () {
    $person = insightPerson();
    $others = collect(range(1, 4))->map(fn () => insightPerson());
    insightFunction($person, ...$others);
    insightAbsence($others[0], 'submitted', ['status' => 'rejected']);
    insightAbsence($others[1], 'submitted', ['status' => 'cancelled']);
    AbsenceRequest::factory()->forUser($others[2])->create(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']);
    insightAbsence($others[3], 'approved');
    $others[3]->update(['is_active' => false]);
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.calendarAll']));

    $this->postJson('/api/absence-requests/preview', absencePayload())->assertOk()->assertJsonCount(0, 'data.colleagues_absent');
});

it('0304 AC-005: colleagues and summary cost the same number of queries with more people', function () {
    $person = insightPerson();
    $actor = grantAbsencePermissions($person, ['absence-requests.calendarAll']);
    $first = insightPerson();
    insightFunction($person, $first);
    insightAbsence($first, 'approved', ['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
    Sanctum::actingAs($actor);

    $count = function (): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->postJson('/api/absence-requests/preview', absencePayload())->assertOk();
        $preview = count(DB::getQueryLog());
        DB::flushQueryLog();
        test()->getJson('/api/absences/summary')->assertOk();

        return [$preview, count(DB::getQueryLog())];
    };

    $count(); // warm-up: permission and config caches
    $few = $count();

    foreach (range(1, 6) as $ignored) {
        $other = insightPerson();
        insightFunction($person, $other);
        insightAbsence($other, 'approved', ['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
    }

    expect($count())->toBe($few);
});

it('0304 AC-006: the insight of a request is 404 to a stranger and fixed for the approver', function () {
    $approver = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $approver);
    $type = AbsenceType::factory()->create(['min_notice_days' => 7]);
    $request = absenceSubmitted($person, $approver, ['absence_type_id' => $type->id, 'minutes' => 777, 'is_short_notice' => true]);
    $colleague = insightPerson();
    insightFunction($person, $colleague);
    insightAbsence($colleague, 'approved');

    Sanctum::actingAs(absenceUserWith());
    $this->getJson("/api/absence-requests/{$request->id}/insight")->assertNotFound();

    Sanctum::actingAs(grantAbsencePermissions($approver, ['absence-requests.calendarAll']));
    $this->getJson("/api/absence-requests/{$request->id}/insight")
        ->assertOk()
        ->assertJsonPath('data.person.id', $person->id)
        ->assertJsonPath('data.minutes', 777)
        ->assertJsonPath('data.notice', ['min_notice_days' => 7, 'is_short_notice' => true])
        ->assertJsonPath('data.balance.residual_after_minutes', $this->getJson("/api/absence-requests/{$request->id}/insight")->json('data.balance.residual_minutes') - 777)
        ->assertJsonPath('data.colleagues_absent.0.user.id', $colleague->id)
        ->assertJsonPath('data.overlaps', []);
});

it('0304 AC-006: an approved request is already part of the residual', function () {
    $approver = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $approver);
    $request = absenceSubmitted($person, $approver, ['status' => 'approved', 'minutes' => 480]);
    Sanctum::actingAs($approver);

    $balance = $this->getJson("/api/absence-requests/{$request->id}/insight")->assertOk()->json('data.balance');

    expect($balance['residual_after_minutes'])->toBe($balance['residual_minutes']);
});

it('0304 AC-006: the insight of a draft is computed live', function () {
    $person = insightPerson();
    $draft = AbsenceRequest::factory()->forUser($person)->create(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-18']);
    Sanctum::actingAs($person);

    $this->getJson("/api/absence-requests/{$draft->id}/insight")->assertOk()->assertJsonPath('data.minutes', 5 * 480);
});
