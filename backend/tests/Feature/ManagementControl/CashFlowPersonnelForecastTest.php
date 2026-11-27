<?php

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0434, AC-001..AC-004 — the personnel outflows estimated from the last imported month.
uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(mcActor());
    $this->company = Company::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

it('AC-001: salaries, thirteenth and F24 are estimated from the base month', function () {
    cfpToday('2026-11-01');
    cfpPerson($this->company);

    $data = mcGet('cash-flow')->assertOk()->json('data');
    $months = cfpByMonth($data);

    // November: the F24 of October (16/11); the net of October is the real installment
    expect($months['2026-11'])->toEqual(['salaries' => 0, 'f24' => 300])
        // December: the net of November (15/12) + the thirteenth (20/12); the F24 of November (16/12)
        ->and($months['2026-12'])->toEqual(['salaries' => 3600, 'f24' => 300])
        ->and($months['2027-01'])->toEqual(['salaries' => 1800, 'f24' => 300])
        ->and($months['2027-04'])->toEqual(['salaries' => 1800, 'f24' => 300])
        ->and($data['backlog'])->toMatchArray(['salaries' => 0, 'f24' => 0])
        // May is the month past the horizon
        ->and($data['beyond'])->toMatchArray(['salaries' => 1800, 'f24' => 300, 'net' => -2100])
        ->and($data['months'][1])->toMatchArray(['payable' => 0, 'net' => -3900]);
});

it('AC-001: the fourteenth is paid on 15 July', function () {
    cfpToday('2027-06-10');
    cfpPerson($this->company);

    $months = cfpByMonth(mcGet('cash-flow', ['horizon_months' => 3])->assertOk()->json('data'));

    expect($months['2027-07']['salaries'])->toEqual(3600)
        ->and($months['2027-08']['salaries'])->toEqual(1800);
});

it('AC-002: an estimate dated before today is omitted, one past the horizon goes to beyond', function () {
    cfpToday('2026-11-20');
    cfpPerson($this->company);

    $data = mcGet('cash-flow', ['horizon_months' => 1])->assertOk()->json('data');

    expect(cfpByMonth($data)['2026-11'])->toEqual(['salaries' => 0, 'f24' => 0])
        ->and($data['backlog'])->toMatchArray(['salaries' => 0, 'f24' => 0])
        ->and($data['beyond'])->toMatchArray(['salaries' => 3600, 'f24' => 300]);
});

it('AC-003: the real regular payslips of a month replace the estimated net, the real net feeds the F24', function () {
    cfpToday('2026-11-01');
    $user = cfpPerson($this->company);
    cfpPayslip($this->company, $user, '2026-11', '1750.00', '2026-12-15');

    $months = cfpByMonth(mcGet('cash-flow')->assertOk()->json('data'));

    // December: only the thirteenth (the net of November is the real installment)
    expect($months['2026-12']['salaries'])->toEqual(1800)
        // F24 of November: base cost 2400 - real net 1750 - 1750 x 2/12 = 291.67
        ->and($months['2026-12']['f24'])->toEqual(358.33)
        ->and($months['2027-01']['salaries'])->toEqual(1800);
});

it('AC-003: the real cost of a month feeds the F24', function () {
    cfpToday('2026-11-01');
    $user = cfpPerson($this->company);
    cfpCost($this->company, $user, '2026-11', '2500.00');

    expect(cfpByMonth(mcGet('cash-flow')->assertOk()->json('data'))['2026-12'])->toEqual(['salaries' => 3600, 'f24' => 400]);
});

it('AC-003: a non-regular payslip of December removes the estimated thirteenth', function () {
    cfpToday('2026-11-01');
    $user = cfpPerson($this->company);
    cfpPayslip($this->company, $user, '2026-12', '1800.00', '2026-12-20', 'TREDICESIMA');

    expect(cfpByMonth(mcGet('cash-flow')->assertOk()->json('data'))['2026-12']['salaries'])->toEqual(1800);
});

it('AC-004: a person terminated mid-December is estimated until the December competence, without thirteenth', function (string $where) {
    cfpToday('2026-11-01');
    if ($where === 'cost') {
        cfpPerson($this->company, costAttributes: ['terminated_on' => '2026-12-15']);
    } else {
        cfpTerminatedProfile(cfpPerson($this->company), '2026-12-15');
    }

    $months = cfpByMonth(mcGet('cash-flow')->assertOk()->json('data'));

    expect($months['2026-12'])->toEqual(['salaries' => 1800, 'f24' => 300])
        ->and($months['2027-01'])->toEqual(['salaries' => 1800, 'f24' => 300])
        ->and($months['2027-02'])->toEqual(['salaries' => 0, 'f24' => 0]);
})->with(['cost', 'profile']);

it('AC-004: a person without a regular payslip in the base period is not estimated', function () {
    cfpToday('2026-11-01');
    cfpPerson($this->company);
    cfpCost($this->company, User::factory()->create(), '2026-10', '3000.00');

    $data = mcGet('cash-flow')->assertOk()->json('data');

    expect(cfpByMonth($data)['2026-12'])->toEqual(['salaries' => 3600, 'f24' => 300]);
});

it('AC-004: without a base period nothing is estimated', function () {
    cfpToday('2026-11-01');
    cfpCost($this->company, User::factory()->create(), '2026-10', '3000.00');

    $data = mcGet('cash-flow')->assertOk()->json('data');

    expect(array_sum(array_column($data['months'], 'salaries')) + array_sum(array_column($data['months'], 'f24')))->toEqual(0);
});
