<?php

use App\Models\Company;
use App\Models\Referent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0272, AC-006 / AC-007 / AC-008 — the agents report.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

const AGENTS_PERIOD = ['date_from' => '2026-01-01', 'date_to' => '2026-03-31'];

it('AC-006: accrued, to settle, in statement, approved and paid follow the registered movements and statements', function () {
    $agent = Referent::factory()->create(['name' => 'Rossi']);
    $company = Company::factory()->create();

    mcAgentAccrual($agent, '100.00', '2026-03-10');
    mcAgentAccrual($agent, '-30.00', '2026-03-20', ['type' => 'REVERSAL']);
    mcAgentAccrual($agent, '50.00', '2025-11-15');
    mcAgentAccrual($agent, '25.00', '2026-03-05', ['settled_externally_on' => '2026-03-06']);
    $issued = mcAgentSettlement($agent, 'ISSUED', '40.00', $company);
    mcAgentAccrual($agent, '40.00', '2026-03-12', ['commission_settlement_id' => $issued->id]);
    mcAgentSettlement($agent, 'DOCUMENT_RECEIVED', '60.00', $company);
    mcAgentSettlement($agent, 'APPROVED', '200.00', $company, mcAgentPayable([['100.00', '80.00', '2026-03-15'], ['100.00', '50.00', '2026-05-10']]));
    mcAgentSettlement($agent, 'CANCELLED', '999.00', $company, mcAgentPayable([['10.00', '10.00', '2026-03-16']]));
    mcAgentSettlement($agent, 'DRAFT', '500.00', $company);

    $data = mcGet('agents', AGENTS_PERIOD)->assertOk()->json('data');

    expect($data['agents'])->toHaveCount(1)
        ->and($data['agents'][0]['referent'])->toBe(['id' => $agent->id, 'name' => 'Rossi'])
        ->and($data['agents'][0]['accrued'])->toEqual(135)
        ->and($data['agents'][0]['to_settle'])->toEqual(120)
        ->and($data['agents'][0]['in_statement'])->toEqual(100)
        ->and($data['agents'][0]['pending_document_count'])->toBe(1)
        ->and($data['agents'][0]['approved_unpaid'])->toEqual(70)
        ->and($data['agents'][0]['paid'])->toEqual(80)
        ->and($data['others'])->toBeNull()
        ->and($data['totals'])->toEqual([
            'accrued' => 135, 'to_settle' => 120, 'in_statement' => 100, 'approved_unpaid' => 70, 'paid' => 80,
            'agents_count' => 1, 'pending_document_count' => 1,
        ]);
});

it('AC-006: the flows are bounded by the period while the statement figures are as of today', function () {
    $agent = Referent::factory()->create();
    mcAgentAccrual($agent, '100.00', '2026-02-10');
    mcAgentAccrual($agent, '10.00', '2026-04-10');
    mcAgentSettlement($agent, 'APPROVED', '90.00', null, mcAgentPayable([['90.00', '90.00', '2026-04-20']]));

    $data = mcGet('agents', AGENTS_PERIOD)->assertOk()->json('data');

    expect($data['totals']['accrued'])->toEqual(100)
        ->and($data['totals']['to_settle'])->toEqual(110)
        ->and($data['totals']['paid'])->toEqual(0)
        ->and($data['totals']['approved_unpaid'])->toEqual(0);
});

it('AC-007: the top agents by amount to settle are listed, the rest is summed and the totals add up', function () {
    [$first, $second, $third] = [Referent::factory()->create(['name' => 'Alfa']), Referent::factory()->create(['name' => 'Beta']), Referent::factory()->create(['name' => 'Gamma'])];
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    mcAgentAccrual($first, '300.00', '2026-01-10', ['company_id' => $companyA->id]);
    mcAgentAccrual($second, '200.00', '2026-02-10', ['company_id' => $companyA->id]);
    mcAgentAccrual($third, '100.00', '2026-02-12', ['company_id' => $companyB->id]);
    mcAgentSettlement($third, 'ISSUED', '70.00', $companyB);
    mcAgentSettlement($third, 'APPROVED', '30.00', $companyB, mcAgentPayable([['30.00', '30.00', '2026-03-03']]));

    $data = mcGet('agents', [...AGENTS_PERIOD, 'top' => 2])->assertOk()->json('data');

    expect(array_column($data['agents'], 'to_settle'))->toEqual([300, 200])
        ->and(array_column(array_column($data['agents'], 'referent'), 'name'))->toBe(['Alfa', 'Beta'])
        ->and($data['others'])->toEqual([
            'accrued' => 100, 'to_settle' => 100, 'in_statement' => 70, 'approved_unpaid' => 0, 'paid' => 30, 'agents_count' => 1, 'pending_document_count' => 1,
        ])
        ->and($data['totals'])->toMatchArray(['accrued' => 600, 'to_settle' => 600, 'in_statement' => 70, 'paid' => 30, 'agents_count' => 3, 'pending_document_count' => 1])
        ->and(array_sum(array_column($data['agents'], 'pending_document_count')) + $data['others']['pending_document_count'])->toBe($data['totals']['pending_document_count']);

    foreach (['accrued', 'to_settle', 'in_statement', 'approved_unpaid', 'paid'] as $figure) {
        expect(round(array_sum(array_column($data['agents'], $figure)) + $data['others'][$figure], 2))->toEqual($data['totals'][$figure]);
    }

    expect(array_column($data['by_month'], 'month'))->toBe(['2026-01', '2026-02', '2026-03'])
        ->and(array_column($data['by_month'], 'accrued'))->toEqual([300, 300, 0])
        ->and(array_column($data['by_month'], 'paid'))->toEqual([0, 0, 30])
        ->and(round(array_sum(array_column($data['by_month'], 'accrued')), 2))->toEqual($data['totals']['accrued'])
        ->and(round(array_sum(array_column($data['by_month'], 'paid')), 2))->toEqual($data['totals']['paid']);

    $everyone = mcGet('agents', [...AGENTS_PERIOD, 'top' => 3])->assertOk()->json('data');
    expect($everyone['agents'])->toHaveCount(3)->and($everyone['others'])->toBeNull();
});

it('AC-007: the company filter applies to movements and statements', function () {
    [$first, $second] = [Referent::factory()->create(), Referent::factory()->create()];
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    mcAgentAccrual($first, '300.00', '2026-01-10', ['company_id' => $companyA->id]);
    mcAgentAccrual($first, '70.00', '2026-01-11', ['company_id' => $companyB->id]);
    mcAgentSettlement($first, 'ISSUED', '40.00', $companyA);
    mcAgentSettlement($first, 'ISSUED', '5.00', $companyB);
    mcAgentAccrual($second, '9.00', '2026-01-12', ['company_id' => $companyB->id]);

    $data = mcGet('agents', [...AGENTS_PERIOD, 'company_id' => [$companyA->id]])->assertOk()->json('data');

    expect($data['agents'])->toHaveCount(1)
        ->and($data['agents'][0]['accrued'])->toEqual(300)
        ->and($data['agents'][0]['in_statement'])->toEqual(40)
        ->and($data['applied']['company_id'])->toBe([$companyA->id]);
});

it('AC-007: an open period spans the months with data, an empty database gives empty lists', function () {
    $empty = mcGet('agents')->assertOk()->json('data');
    expect($empty['agents'])->toBe([])->and($empty['others'])->toBeNull()->and($empty['by_month'])->toBe([])
        ->and($empty['totals'])->toEqual(['accrued' => 0, 'to_settle' => 0, 'in_statement' => 0, 'approved_unpaid' => 0, 'paid' => 0, 'agents_count' => 0, 'pending_document_count' => 0]);

    $agent = Referent::factory()->create();
    mcAgentAccrual($agent, '10.00', '2026-01-10');
    mcAgentAccrual($agent, '10.00', '2026-03-10');

    expect(array_column(mcGet('agents')->assertOk()->json('data.by_month'), 'month'))->toBe(['2026-01', '2026-02', '2026-03']);
});

it('AC-008: the number of queries does not depend on the agents and movements', function () {
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $agent = Referent::factory()->create();
            mcAgentAccrual($agent, '10.00', '2026-02-10');
            mcAgentSettlement($agent, 'ISSUED', '5.00');
            mcAgentSettlement($agent, 'APPROVED', '20.00', null, mcAgentPayable([['20.00', '10.00', '2026-02-20']]));
        }
    };

    $seed(5);
    $few = mcQueryCount('agents', AGENTS_PERIOD);
    $seed(20);

    expect(mcQueryCount('agents', AGENTS_PERIOD))->toBe($few);
});
