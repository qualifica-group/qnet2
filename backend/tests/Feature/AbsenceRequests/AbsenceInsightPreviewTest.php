<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use App\Models\User;
use App\Services\Absences\Balances\AbsenceBalanceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('0304 AC-004: the preview counts working days, lists non-working ones and the balance after', function () {
    $person = insightPerson();
    $type = AbsenceType::factory()->create(['deducts_balance' => true]);
    $residual = (int) app(AbsenceBalanceBuilder::class)->row($person, $type, 2026)['residual_minutes'];
    Sanctum::actingAs($person);
    $before = AbsenceRequest::count();

    $this->postJson('/api/absence-requests/preview', absencePayload(['absence_type_id' => $type->id, 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-11']))
        ->assertOk()
        ->assertJsonPath('data.person.id', $person->id)
        ->assertJsonPath('data.daily_minutes', 480)
        ->assertJsonPath('data.working_days', 4)
        ->assertJsonPath('data.minutes', 4 * 480)
        ->assertJsonPath('data.non_working_days', [['date' => '2026-12-08', 'reason' => 'holiday', 'name' => null]])
        ->assertJsonPath('data.balance', ['year' => 2026, 'residual_minutes' => $residual, 'residual_after_minutes' => $residual - 4 * 480])
        ->assertJsonPath('data.notice', ['min_notice_days' => null, 'is_short_notice' => false])
        ->assertJsonPath('data.overlaps', [])
        ->assertJsonPath('data.colleagues_absent', []);

    expect(AbsenceRequest::count())->toBe($before);
});

it('0304 AC-004: closure days carry their name, a half day counts 1 working day and 240 minutes', function () {
    $person = insightPerson();
    CompanyClosure::factory()->create(['name' => 'Bridge', 'starts_on' => '2026-12-15', 'ends_on' => '2026-12-15']);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests/preview', absencePayload(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-16']))
        ->assertOk()->assertJsonPath('data.working_days', 2)
        ->assertJsonPath('data.non_working_days', [['date' => '2026-12-15', 'reason' => 'closure', 'name' => 'Bridge']]);
    $this->postJson('/api/absence-requests/preview', absencePayload(['is_full_day' => false, 'day_part' => 'morning']))
        ->assertOk()->assertJsonPath('data.working_days', 1)->assertJsonPath('data.minutes', 240);
});

it('0304 AC-004: a type that does not deduct has a null balance, short notice is reported', function () {
    $person = insightPerson();
    $type = AbsenceType::factory()->notDeducting()->create(['min_notice_days' => 7]);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests/preview', absencePayload(['absence_type_id' => $type->id, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26']))
        ->assertOk()->assertJsonPath('data.balance', null)
        ->assertJsonPath('data.notice', ['min_notice_days' => 7, 'is_short_notice' => true]);
});

it('0304 AC-004: own approved overlaps are listed (not a 422) and request_id excludes the draft itself', function () {
    $person = insightPerson();
    $approved = insightAbsence($person, 'approved', ['starts_on' => '2026-12-14', 'ends_on' => '2026-12-15']);
    $own = insightAbsence($person, 'submitted', ['starts_on' => '2026-12-16', 'ends_on' => '2026-12-16']);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests/preview', absencePayload(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-16']))
        ->assertOk()
        ->assertJsonPath('data.overlaps.0.request_id', $approved->id)
        ->assertJsonPath('data.overlaps.0.status', 'approved')
        ->assertJsonCount(2, 'data.overlaps');
    $this->postJson('/api/absence-requests/preview', absencePayload(['starts_on' => '2026-12-16', 'ends_on' => '2026-12-16', 'request_id' => $own->id]))
        ->assertOk()->assertJsonCount(0, 'data.overlaps');
});

it('0304 AC-004: preview errors are 422 on the form fields, 403 without create or for another person without manageAll', function () {
    $person = insightPerson();
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests/preview', absencePayload(['starts_on' => '2026-12-12', 'ends_on' => '2026-12-13']))
        ->assertStatus(422)->assertJsonValidationErrors('starts_on');
    $this->postJson('/api/absence-requests/preview', [])->assertStatus(422)->assertJsonValidationErrors(['absence_type_id', 'is_full_day', 'starts_on', 'ends_on']);
    $this->postJson('/api/absence-requests/preview', absencePayload(['absence_type_id' => AbsenceType::factory()->create(['allows_full_day' => false])->id]))
        ->assertStatus(422)->assertJsonValidationErrors('is_full_day');
    $this->postJson('/api/absence-requests/preview', absencePayload(['user_id' => User::factory()->create()->id]))->assertForbidden();

    Sanctum::actingAs(absenceUserWith(['viewAny']));
    $this->postJson('/api/absence-requests/preview', absencePayload())->assertForbidden();

    Sanctum::actingAs(absenceUserWith(['viewAny', 'create', 'manageAll']));
    $this->postJson('/api/absence-requests/preview', absencePayload(['user_id' => $person->id]))
        ->assertOk()->assertJsonPath('data.person.id', $person->id);
});
