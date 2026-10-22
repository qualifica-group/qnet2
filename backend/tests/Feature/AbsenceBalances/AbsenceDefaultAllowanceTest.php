<?php

use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-12-20 10:00:00'));

it('0304 AC-010: the type default in days scales on the daily minutes of the person', function () {
    $type = AbsenceType::factory()->create(['default_annual_days' => 26, 'color' => 'emerald']);
    $full = balanceDailyPerson(480);
    $part = balanceDailyPerson(240);
    $set = balanceDailyPerson(480);
    AbsenceAllowance::factory()->create(['user_id' => $set->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 6000]);
    Sanctum::actingAs(grantAbsencePermissions($full, ['absence-requests.viewAny']));

    $this->getJson('/api/absence-balances?year=2026')
        ->assertOk()
        ->assertJsonPath('data.daily_minutes', 480)
        ->assertJsonPath('data.balances.0.annual_minutes', 12480)
        ->assertJsonPath('data.balances.0.source', 'default')
        ->assertJsonPath('data.balances.0.absence_type.color', 'emerald')
        ->assertJsonPath('data.balances.0.accrued_minutes', 12480);

    Sanctum::actingAs(grantAbsencePermissions($part, ['absence-requests.viewAny']));
    $this->getJson('/api/absence-balances?year=2026')
        ->assertJsonPath('data.daily_minutes', 240)
        ->assertJsonPath('data.balances.0.annual_minutes', 6240);

    Sanctum::actingAs(grantAbsencePermissions($set, ['absence-requests.viewAny']));
    $this->getJson('/api/absence-balances?year=2026')
        ->assertJsonPath('data.balances.0.annual_minutes', 6000)
        ->assertJsonPath('data.balances.0.source', 'allowance');
});

it('0304 AC-010: a type without default and without allowance has source none and zero', function () {
    AbsenceType::factory()->create(['default_annual_days' => null]);
    $person = balanceDailyPerson(480);
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.viewAny']));

    $this->getJson('/api/absence-balances?year=2026')
        ->assertJsonPath('data.balances.0.annual_minutes', 0)
        ->assertJsonPath('data.balances.0.source', 'none');
});

it('0304 AC-010: PUT allowance with a null annual_minutes returns to the default, 422 when omitted', function () {
    $type = AbsenceType::factory()->create(['default_annual_days' => 26]);
    $person = balanceDailyPerson(480);
    AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 6000]);
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.manageAll']));
    $payload = ['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'carried_over_minutes' => 0];

    $this->putJson('/api/absence-allowances', $payload)->assertUnprocessable();
    $this->putJson('/api/absence-allowances', [...$payload, 'annual_minutes' => null])
        ->assertOk()
        ->assertJsonPath('data.annual_minutes', 12480)
        ->assertJsonPath('data.source', 'default');

    expect(AbsenceAllowance::query()->sole()->annual_minutes)->toBeNull();
});

it('0304 AC-011: bulk sets days times the daily minutes of each person and keeps the carry-over', function () {
    $type = AbsenceType::factory()->create(['default_annual_days' => 26]);
    $a = balanceDailyPerson(480);
    $b = balanceDailyPerson(240);
    $c = balanceDailyPerson(450);
    AbsenceAllowance::factory()->create(['user_id' => $a->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 100, 'carried_over_minutes' => 77]);
    Sanctum::actingAs(grantAbsencePermissions($a, ['absence-requests.manageAll']));
    $payload = ['absence_type_id' => $type->id, 'year' => 2026, 'user_ids' => [$a->id, $b->id, $c->id], 'annual_days' => 20];

    $this->putJson('/api/absence-allowances/bulk', $payload)->assertOk()->assertJsonPath('data.updated', 3);

    $rows = AbsenceAllowance::query()->where('year', 2026)->get()->keyBy('user_id');
    expect($rows)->toHaveCount(3)
        ->and($rows[$a->id]->annual_minutes)->toBe(9600)
        ->and($rows[$a->id]->carried_over_minutes)->toBe(77)
        ->and($rows[$b->id]->annual_minutes)->toBe(4800)
        ->and($rows[$b->id]->carried_over_minutes)->toBe(0)
        ->and($rows[$c->id]->annual_minutes)->toBe(9000);

    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'annual_days' => null])->assertOk();
    expect(AbsenceAllowance::query()->where('year', 2026)->whereNotNull('annual_minutes')->count())->toBe(0)
        ->and($rows[$a->id]->fresh()->carried_over_minutes)->toBe(77);
});

it('0304 AC-011: bulk is HR-only and validates the payload and the type', function () {
    $type = AbsenceType::factory()->create();
    $person = balanceDailyPerson(480);
    $payload = ['absence_type_id' => $type->id, 'year' => 2026, 'user_ids' => [$person->id], 'annual_days' => 20];

    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.viewAny']));
    $this->putJson('/api/absence-allowances/bulk', $payload)->assertForbidden();

    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.manageAll']));
    $notDeducting = AbsenceType::factory()->notDeducting()->create();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'absence_type_id' => $notDeducting->id])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'user_ids' => []])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'user_ids' => [$person->id, $person->id]])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'user_ids' => [999999]])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'annual_days' => 367])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'annual_days' => 1.234])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', [...$payload, 'year' => 1999])->assertUnprocessable();
    $this->putJson('/api/absence-allowances/bulk', ['absence_type_id' => $type->id, 'year' => 2026, 'user_ids' => [$person->id]])->assertUnprocessable();
});

it('0304 AC-012: who lives on the default alone gets a 2027 row with null annual and the 2026 residual, once', function () {
    $type = AbsenceType::factory()->create(['default_annual_days' => 26]);
    $person = balanceDailyPerson(480);
    $withRow = balanceDailyPerson(480);
    AbsenceAllowance::factory()->create(['user_id' => $withRow->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => null]);
    AbsenceRequest::factory()->forUser($person)->approved(960)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-02']);

    $this->artisan('absences:carry-over 2027')->assertSuccessful();
    $this->artisan('absences:carry-over 2027')->assertSuccessful();

    $row = AbsenceAllowance::query()->where(['user_id' => $person->id, 'year' => 2027])->sole();
    $copied = AbsenceAllowance::query()->where(['user_id' => $withRow->id, 'year' => 2027])->sole();
    expect($row->annual_minutes)->toBeNull()
        ->and($row->carried_over_minutes)->toBe(12480 - 960)
        ->and($copied->annual_minutes)->toBeNull()
        ->and($copied->carried_over_minutes)->toBe(12480)
        ->and(AbsenceAllowance::query()->where('year', 2027)->count())->toBe(2);
});

it('0304 AC-012: a type without default is not carried for people without a previous row', function () {
    AbsenceType::factory()->create(['default_annual_days' => null]);
    balanceDailyPerson(480);

    $this->artisan('absences:carry-over 2027')->assertSuccessful();

    expect(AbsenceAllowance::query()->count())->toBe(0);
});
