<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0432, AC-005 and AC-006 — companies, filters, switch, rounding and query count.
uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(mcActor());
    cfpToday('2026-11-01');
    $this->alfa = Company::factory()->create(['denomination' => 'Alfa']);
    $this->beta = Company::factory()->create(['denomination' => 'Beta']);
    cfpPerson($this->alfa);
    cfpPerson($this->beta, '1200.00', '900.00');
});

afterEach(fn () => Carbon::setTestNow());

it('AC-005: the estimates go to the company of the cost and the by-company invariants hold', function () {
    $data = mcGet('cash-flow')->assertOk()->json('data');
    $rows = collect($data['by_company'])->keyBy('label');

    // December: Alfa -(1800 + 1800 + 300), Beta -(900 + 900 + 150)
    expect($rows['Alfa']['months'][1]['net'])->toEqual(-3900)
        ->and($rows['Beta']['months'][1]['net'])->toEqual(-1950)
        ->and($data['months'][1]['net'])->toEqual(-5850)
        ->and(round(array_sum(array_column($data['by_company'], 'beyond_net')), 2))->toEqual($data['beyond']['net'])
        ->and(round(array_sum(array_column($data['by_company'], 'backlog_net')), 2))->toEqual($data['backlog']['net']);

    foreach ($data['months'] as $index => $month) {
        expect(round(array_sum(array_map(fn (array $row): float => $row['months'][$index]['net'], $data['by_company'])), 2))->toEqual($month['net']);
    }
});

it('AC-005: the company filter restricts the estimates', function () {
    $data = mcGet('cash-flow', ['company_id' => [$this->beta->id]])->assertOk()->json('data');

    expect(cfpByMonth($data)['2026-12'])->toEqual(['salaries' => 1800, 'f24' => 150])
        ->and(array_column($data['by_company'], 'label'))->toBe(['Beta']);
});

it('AC-005: a site filter excludes the estimates, the document type does not change them', function () {
    $site = CompanySite::factory()->create(['company_id' => $this->alfa->id]);
    $operational = OperationalSite::factory()->create();

    $bySite = mcGet('cash-flow', ['company_site_id' => [$site->id]])->assertOk()->json('data');
    $byOperational = mcGet('cash-flow', ['operational_site_id' => [$operational->id]])->assertOk()->json('data');
    $invoices = mcGet('cash-flow', ['document_type' => 'invoice'])->assertOk()->json('data');
    $all = mcGet('cash-flow')->assertOk()->json('data');

    expect(array_sum(array_column($bySite['months'], 'salaries')))->toEqual(0)
        ->and(array_sum(array_column($byOperational['months'], 'f24')))->toEqual(0)
        ->and(cfpByMonth($invoices))->toEqual(cfpByMonth($all))
        ->and($invoices['beyond'])->toEqual($all['beyond']);
});

it('AC-005: include_personnel=false zeroes the estimates and the net follows', function () {
    $on = mcGet('cash-flow')->assertOk();
    $off = mcGet('cash-flow', ['include_personnel' => false])->assertOk();
    $data = $off->json('data');

    expect($on->json('data.applied.include_personnel'))->toBeTrue()
        ->and($data['applied']['include_personnel'])->toBeFalse()
        ->and(array_sum(array_column($data['months'], 'salaries')) + array_sum(array_column($data['months'], 'f24')))->toEqual(0)
        ->and($data['months'][1]['net'])->toEqual(0)
        ->and($data['beyond']['net'])->toEqual(0)
        ->and(array_sum(array_map(fn (array $row): float => $row['months'][1]['net'], $data['by_company'])))->toEqual(0);
});

it('AC-005: include_personnel must be a boolean', function () {
    mcGet('cash-flow', ['include_personnel' => 'maybe'])->assertStatus(422);
});

it('AC-006: the estimates are rounded per person and month', function () {
    foreach (range(1, 3) as $ignored) {
        cfpPerson($this->alfa, '1500.00', '1000.01');
    }

    // Per person: F24 = 1500.00 - 1000.01 - 166.67 (166.668 rounded) = 333.32; three people + the two of the setup
    expect(cfpByMonth(mcGet('cash-flow')->assertOk()->json('data'))['2026-12']['f24'])->toEqual(round(300 + 150 + 3 * 333.32, 2));
});

it('AC-006: the number of queries does not grow with the people', function () {
    $before = mcQueryCount('cash-flow');

    foreach (range(1, 6) as $ignored) {
        cfpPerson($this->alfa);
    }

    expect(mcQueryCount('cash-flow'))->toBe($before);
});
