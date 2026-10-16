<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-006 — the cash flow by company and the filters on companies and sites.
uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(mcActor());
    $this->scenario = mcExposureScenario();
    // Backlog on Alfa (receivable) and beyond the horizon on Beta (payable)
    mcReceivable([['due' => -5, 'amount' => '25.00']], ['company_id' => $this->scenario['alfa']->id]);
    mcPayable([['due' => mcMonthDue(9), 'amount' => '15.00']], ['company_id' => $this->scenario['beta']->id, 'company_site_id' => $this->scenario['siteTwo']->id]);
});

it('AC-006: the rows by company add up to the global figures in every column', function () {
    $data = mcGet('cash-flow')->assertOk()->json('data');
    $rows = $data['by_company'];

    expect(array_column($rows, 'label'))->toBe(['Alfa', 'Beta'])
        ->and(array_sum(array_column($rows, 'backlog_net')))->toEqual($data['backlog']['net'])
        ->and(array_sum(array_column($rows, 'beyond_net')))->toEqual($data['beyond']['net'])
        ->and($data['backlog']['net'])->toEqual(25)
        ->and($data['beyond']['net'])->toEqual(-15);

    foreach ($data['months'] as $index => $month) {
        expect(round(array_sum(array_map(fn (array $row): float => $row['months'][$index]['net'], $rows)), 2))->toEqual($month['net'])
            ->and($rows[0]['months'][$index]['month'])->toBe($month['month']);
    }

    // Alfa: receivable 100 + 50 - payable 30 - committed 40 + backlog 25; Beta: 200 - 20 - 70 - 15 beyond
    expect($rows[0])->toMatchArray(['backlog_net' => 25, 'beyond_net' => 0, 'total_net' => 105])
        ->and($rows[1])->toMatchArray(['backlog_net' => 0, 'beyond_net' => -15, 'total_net' => 95])
        ->and(array_sum(array_column($rows, 'total_net')))->toEqual(200);
});

it('AC-006: the company and site filters restrict every section', function () {
    $beta = mcGet('cash-flow', ['company_id' => [$this->scenario['beta']->id]])->assertOk()->json('data');
    $bySite = mcGet('cash-flow', ['company_site_id' => [$this->scenario['siteTwo']->id]])->assertOk()->json('data');
    $byOperationalSite = mcGet('cash-flow', ['operational_site_id' => [$this->scenario['torino']->id]])->assertOk()->json('data');

    $total = fn (array $data): float => $data['backlog']['net'] + $data['beyond']['net'] + array_sum(array_column($data['months'], 'net'));

    expect(array_column($beta['by_company'], 'label'))->toBe(['Beta'])
        ->and($total($beta))->toEqual(95)
        ->and($beta['backlog']['receivable'])->toEqual(0)
        ->and(array_column($bySite['by_company'], 'label'))->toBe(['Beta'])
        ->and($total($bySite))->toEqual(95)
        // Torino: receivable 100 (offer), payable 30 and committed 40 by header
        ->and($total($byOperationalSite))->toEqual(30)
        ->and(array_column($byOperationalSite['by_company'], 'label'))->toBe(['Alfa']);
});
