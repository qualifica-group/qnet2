<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-002 / AC-011 — access, validation and query count of the monthly trend endpoint.
uses(RefreshDatabase::class);

it('AC-002: 401 anonymous and 403 without the permission', function () {
    $this->getJson('/api/management-control/monthly-trend')->assertUnauthorized();

    Sanctum::actingAs(mcActor(false));
    mcGet('monthly-trend', ['date_from' => '2026-01-01', 'date_to' => '2026-03-31'])->assertForbidden();
});

it('AC-002: invalid parameters answer 422', function (array $params) {
    Sanctum::actingAs(mcActor());

    mcGet('monthly-trend', $params)->assertUnprocessable();
})->with([
    'no dates' => [[]],
    'date_to missing' => [['date_from' => '2026-01-01']],
    'date_from missing' => [['date_to' => '2026-01-31']],
    'bad date' => [['date_from' => '2026-13-45', 'date_to' => '2026-12-31']],
    'date_to before date_from' => [['date_from' => '2026-03-10', 'date_to' => '2026-03-09']],
    'more than 24 months' => [['date_from' => '2024-01-01', 'date_to' => '2026-01-31']],
    'unknown company' => [['date_from' => '2026-01-01', 'date_to' => '2026-03-31', 'company_id' => [999999]]],
    'unknown company site' => [['date_from' => '2026-01-01', 'date_to' => '2026-03-31', 'company_site_id' => [999999]]],
    'unknown operational site' => [['date_from' => '2026-01-01', 'date_to' => '2026-03-31', 'operational_site_id' => [999999]]],
]);

it('AC-002: the parameters are echoed in applied, 24 months are accepted and an empty period has zeros', function () {
    Sanctum::actingAs(mcActor());
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $data = mcGet('monthly-trend', ['date_from' => '2024-02-01', 'date_to' => '2026-01-31', 'company_id' => [$second->id, $first->id]])
        ->assertOk()->json('data');

    expect($data['applied'])->toBe([
        'date_from' => '2024-02-01', 'date_to' => '2026-01-31', 'company_id' => [$first->id, $second->id], 'company_site_id' => [], 'operational_site_id' => [],
    ])->and($data['months'])->toHaveCount(24)
        ->and($data['months'][0])->toBe('2024-02')
        ->and($data['months'][23])->toBe('2026-01')
        ->and($data['functions'])->toBe([])
        ->and($data['totals']['revenue'])->toEqual(array_fill(0, 24, 0))
        ->and($data['totals']['previous_year']['margin'])->toEqual(array_fill(0, 24, 0));
});

it('AC-011: the number of queries does not depend on the documents and orders', function () {
    Sanctum::actingAs(mcActor());
    $params = ['date_from' => '2026-01-01', 'date_to' => '2026-03-31'];
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $workOrder = mcWorkOrder();
            $line = mcRevenueLine($workOrder, '100.00', BusinessFunction::factory()->create());
            mcTrendInvoice('2026-02-10', [['10.00', $line], ['5.00', null]], ['work_order_id' => $workOrder->id]);
            mcTrendCost('2026-02-11', '7.00', $workOrder);
            mcTrendCost('2025-02-11', '3.00');
        }
    };

    $seed(5);
    $few = mcQueryCount('monthly-trend', $params);
    $seed(20);

    expect(mcQueryCount('monthly-trend', $params))->toBe($few);
});
