<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-002 / AC-011 — access, validation and query count of the cash flow endpoint.
// AC-001 (the two menu entries) lives in ExposureAccessTest, next to the other entry.
uses(RefreshDatabase::class);

it('AC-002: 401 anonymous and 403 without the permission', function () {
    $this->getJson('/api/management-control/cash-flow')->assertUnauthorized();

    Sanctum::actingAs(mcActor(false));
    mcGet('cash-flow')->assertForbidden();
});

it('AC-002: invalid parameters answer 422', function (array $params) {
    Sanctum::actingAs(mcActor());

    mcGet('cash-flow', $params)->assertUnprocessable();
})->with([
    'unknown company' => [['company_id' => [999999]]],
    'unknown company site' => [['company_site_id' => [999999]]],
    'unknown operational site' => [['operational_site_id' => [999999]]],
    'document_type out of list' => [['document_type' => 'credit_note']],
    'horizon zero' => [['horizon_months' => 0]],
    'horizon too high' => [['horizon_months' => 25]],
    'horizon not a number' => [['horizon_months' => 'six']],
]);

it('AC-002: the defaults and the normalised parameters are echoed in applied', function () {
    Sanctum::actingAs(mcActor());
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $default = mcGet('cash-flow')->assertOk()->json('data');
    expect($default['applied'])->toBe([
        'company_id' => [], 'company_site_id' => [], 'operational_site_id' => [], 'document_type' => 'all', 'horizon_months' => 6,
    ])->and($default['today'])->toBe(mcDay(0))
        ->and($default['months'])->toHaveCount(6)
        ->and($default['backlog'])->toEqual(['receivable' => 0, 'payable' => 0, 'committed' => 0, 'net' => 0])
        ->and($default['by_company'])->toBe([]);

    $applied = mcGet('cash-flow', ['company_id' => [$second->id, $first->id, $second->id], 'document_type' => 'invoice', 'horizon_months' => 24])
        ->assertOk()->json('data');

    expect($applied['applied'])->toMatchArray(['company_id' => [$first->id, $second->id], 'document_type' => 'invoice', 'horizon_months' => 24])
        ->and($applied['months'])->toHaveCount(24);
});

it('AC-011: the number of queries does not depend on the documents and orders', function () {
    Sanctum::actingAs(mcActor());
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            mcReceivable([['due' => -$i - 1, 'amount' => '10.00'], ['due' => mcMonthDue(1 + $i % 3), 'amount' => '20.00']]);
            mcPayable([['due' => $i, 'amount' => '5.00']]);
            mcOrderLine(null, '7.00', order: ['order_date' => mcDay(-$i)]);
        }
    };

    $seed(5);
    $few = mcQueryCount('cash-flow');
    $seed(20);

    expect(mcQueryCount('cash-flow'))->toBe($few);
});
