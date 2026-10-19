<?php

use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('0278 AC-014: carries the 2026 residual, even negative, into 2027 and keeps the annual allowance', function () {
    $this->travelTo('2026-12-20 10:00:00');
    $type = AbsenceType::factory()->create();
    $positive = balancePerson('2020-01-01');
    $negative = balancePerson('2020-01-01');
    AbsenceAllowance::factory()->create(['user_id' => $positive->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 9600, 'carried_over_minutes' => 100]);
    AbsenceAllowance::factory()->create(['user_id' => $negative->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 1200, 'carried_over_minutes' => 0]);
    AbsenceRequest::factory()->forUser($positive)->approved(1000)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-01']);
    AbsenceRequest::factory()->forUser($negative)->approved(2000)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-01']);

    $this->artisan('absences:carry-over 2027')->assertSuccessful();

    $a = AbsenceAllowance::query()->where(['user_id' => $positive->id, 'year' => 2027])->sole();
    $b = AbsenceAllowance::query()->where(['user_id' => $negative->id, 'year' => 2027])->sole();
    expect($a->carried_over_minutes)->toBe(100 + 9600 - 1000)
        ->and($a->annual_minutes)->toBe(9600)
        ->and($b->carried_over_minutes)->toBe(1200 - 2000)
        ->and($b->annual_minutes)->toBe(1200);
});

it('0278 AC-014: re-running neither duplicates nor changes values, and never overwrites an HR-set annual allowance', function () {
    $this->travelTo('2026-12-20 10:00:00');
    $type = AbsenceType::factory()->create();
    $person = balancePerson('2020-01-01');
    AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 9600]);
    AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2027, 'annual_minutes' => 7200, 'carried_over_minutes' => 5]);

    $this->artisan('absences:carry-over 2027')->assertSuccessful();
    $this->artisan('absences:carry-over 2027')->assertSuccessful();

    $row = AbsenceAllowance::query()->where(['user_id' => $person->id, 'year' => 2027])->sole();
    expect(AbsenceAllowance::query()->count())->toBe(2)
        ->and($row->annual_minutes)->toBe(7200)
        ->and($row->carried_over_minutes)->toBe(9600);
});

it('0278 D-11: defaults to the current year and rejects a bad year', function () {
    $this->travelTo('2027-01-01 00:45:00');
    $type = AbsenceType::factory()->create();
    $person = balancePerson(null);
    AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 600]);

    $this->artisan('absences:carry-over')->assertSuccessful();
    expect(AbsenceAllowance::query()->where('year', 2027)->count())->toBe(1);

    $this->artisan('absences:carry-over abc')->assertFailed();
});
