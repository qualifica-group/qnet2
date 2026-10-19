<?php

use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-10-19 10:00:00'));

it('0278 AC-012: accrues 8 months for a March 10th hire and sums used and pending', function () {
    $person = balancePerson('2026-03-10');
    $type = AbsenceType::factory()->create(['name' => 'Ferie']);
    AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 9600, 'carried_over_minutes' => 120]);
    AbsenceRequest::factory()->forUser($person)->approved(960)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-05-04', 'ends_on' => '2026-05-05']);
    AbsenceRequest::factory()->forUser($person)->submitted(480)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-01']);
    AbsenceRequest::factory()->forUser($person)->approved(480)->create(['absence_type_id' => $type->id, 'starts_on' => '2025-05-05', 'ends_on' => '2025-05-05']);
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.viewAny']));

    $this->getJson('/api/absence-balances')
        ->assertOk()
        ->assertJsonPath('data.user.id', $person->id)
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.balances.0.absence_type', ['id' => $type->id, 'name' => 'Ferie'])
        ->assertJsonPath('data.balances.0.annual_minutes', 9600)
        ->assertJsonPath('data.balances.0.carried_over_minutes', 120)
        ->assertJsonPath('data.balances.0.accrued_minutes', 6400)
        ->assertJsonPath('data.balances.0.used_minutes', 960)
        ->assertJsonPath('data.balances.0.pending_minutes', 480)
        ->assertJsonPath('data.balances.0.residual_minutes', 120 + 6400 - 960);
});

it('0278 AC-012: a March 20th hire does not accrue March', function () {
    $person = balancePerson('2026-03-20');
    $type = AbsenceType::factory()->create();
    AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 9600]);
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.viewAny']));

    $this->getJson('/api/absence-balances')
        ->assertOk()
        ->assertJsonPath('data.balances.0.accrued_minutes', 5600);
});

it('0278 D-1: lists one zeroed row per active deducting type and skips the others', function () {
    $person = balancePerson(null);
    $deducting = AbsenceType::factory()->create(['name' => 'Ferie', 'sort_order' => 1]);
    AbsenceType::factory()->notDeducting()->create();
    AbsenceType::factory()->inactive()->create();
    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.viewAny']));

    $response = $this->getJson('/api/absence-balances?year=2025')->assertOk();

    expect($response->json('data.balances'))->toHaveCount(1)
        ->and($response->json('data.balances.0'))->toMatchArray([
            'annual_minutes' => 0, 'carried_over_minutes' => 0, 'accrued_minutes' => 0,
            'used_minutes' => 0, 'pending_minutes' => 0, 'residual_minutes' => 0,
        ])
        ->and($response->json('data.balances.0.absence_type.id'))->toBe($deducting->id);
});

it('0278 AC-013: a manager reads a descendant balance, a stranger gets 403, the HR office reads anyone', function () {
    $manager = orgChartActor(['absence-requests.viewAny']);
    $member = User::factory()->create();
    EmploymentProfile::factory()->for($member)->reportsTo($manager)->create();
    $stranger = orgChartActor(['absence-requests.viewAny']);
    $hr = orgChartActor(['absence-requests.viewAny', 'absence-requests.manageAll']);
    AbsenceType::factory()->create();

    Sanctum::actingAs($manager);
    $this->getJson("/api/absence-balances?user_id={$member->id}")->assertOk()->assertJsonPath('data.user.id', $member->id);

    Sanctum::actingAs($stranger);
    $this->getJson("/api/absence-balances?user_id={$member->id}")->assertForbidden();

    Sanctum::actingAs($hr);
    $this->getJson("/api/absence-balances?user_id={$member->id}")->assertOk();
});

it('0278 D-1: balances need viewAny and a valid year', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/absence-balances')->assertForbidden();

    Sanctum::actingAs(orgChartActor(['absence-requests.viewAny']));
    $this->getJson('/api/absence-balances?year=1999')->assertUnprocessable();
    $this->getJson('/api/absence-balances?user_id=999999')->assertUnprocessable();
});

it('0278 AC-013: only the HR office upserts an allowance, answering with the balance row and logging it', function () {
    $person = User::factory()->create();
    $type = AbsenceType::factory()->create(['name' => 'Ferie']);
    $payload = ['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 9600, 'carried_over_minutes' => -60];

    Sanctum::actingAs(orgChartActor(['absence-requests.viewAny']));
    $this->putJson('/api/absence-allowances', $payload)->assertForbidden();

    Sanctum::actingAs(orgChartActor(['absence-requests.manageAll']));
    $this->putJson('/api/absence-allowances', $payload)
        ->assertOk()
        ->assertJsonPath('data.absence_type.id', $type->id)
        ->assertJsonPath('data.annual_minutes', 9600)
        ->assertJsonPath('data.carried_over_minutes', -60)
        ->assertJsonPath('data.accrued_minutes', 8000)
        ->assertJsonPath('data.residual_minutes', 7940);

    $this->putJson('/api/absence-allowances', [...$payload, 'annual_minutes' => 4800])->assertOk();

    expect(AbsenceAllowance::query()->count())->toBe(1)
        ->and(AbsenceAllowance::query()->sole()->annual_minutes)->toBe(4800)
        ->and(Activity::query()->where('subject_type', (new AbsenceAllowance)->getMorphClass())->count())->toBe(2);
});

it('0278 AC-013: a non deducting type and out-of-range values are 422', function () {
    $person = User::factory()->create();
    $type = AbsenceType::factory()->notDeducting()->create();
    $valid = AbsenceType::factory()->create();
    $payload = ['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 100, 'carried_over_minutes' => 0];
    Sanctum::actingAs(orgChartActor(['absence-requests.manageAll']));

    $this->putJson('/api/absence-allowances', $payload)->assertUnprocessable()->assertJsonValidationErrors('absence_type_id');
    $this->putJson('/api/absence-allowances', [...$payload, 'absence_type_id' => $valid->id, 'annual_minutes' => -1])->assertUnprocessable();
    $this->putJson('/api/absence-allowances', [...$payload, 'absence_type_id' => $valid->id, 'annual_minutes' => 525601])->assertUnprocessable();
    $this->putJson('/api/absence-allowances', [...$payload, 'absence_type_id' => $valid->id, 'year' => 1999])->assertUnprocessable();
    $this->putJson('/api/absence-allowances', [...$payload, 'absence_type_id' => $valid->id, 'carried_over_minutes' => -525601])->assertUnprocessable();
    $this->putJson('/api/absence-allowances', [])->assertUnprocessable();
});
