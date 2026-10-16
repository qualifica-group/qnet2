<?php

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0271, AC-002 / AC-009 — the menu entries, access, validation and query count of the billing endpoint.
uses(RefreshDatabase::class);

it('AC-002: the billing and accounts entries follow management-control.view and permissions:sync adds no permission', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'like', 'management-control.%')->pluck('name')->all())->toBe(['management-control.view']);

    Sanctum::actingAs(User::factory()->create());
    $keys = navigationNodeKeys($this->getJson('/api/navigation')->json('data'));
    expect($keys)->not->toContain('management-control-billing')->not->toContain('management-control-accounts');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('management-control.view'));
    $section = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'management-control-section');

    expect($section['children'][4])->toMatchArray([
        'key' => 'management-control-billing', 'label' => 'navigation.billing',
        'route' => '/management-control/billing', 'icon' => 'file-text',
    ])->and($section['children'][5])->toMatchArray([
        'key' => 'management-control-accounts', 'label' => 'navigation.accounts',
        'route' => '/management-control/accounts', 'icon' => 'landmark',
    ]);
});

it('AC-002: 401 anonymous and 403 without the permission', function (string $endpoint) {
    $this->getJson("/api/management-control/{$endpoint}")->assertUnauthorized();

    Sanctum::actingAs(mcActor(false));
    mcGet($endpoint)->assertForbidden();
})->with(['billing', 'account-flows']);

it('AC-002: invalid parameters answer 422', function (array $params) {
    Sanctum::actingAs(mcActor());

    mcGet('billing', $params)->assertUnprocessable();
})->with([
    'bad date' => [['date_from' => '2026-13-45']],
    'date_to before date_from' => [['date_from' => '2026-03-10', 'date_to' => '2026-03-09']],
    'unknown company' => [['company_id' => [999999]]],
    'unknown company site' => [['company_site_id' => [999999]]],
    'unknown operational site' => [['operational_site_id' => [999999]]],
    'group_by out of list' => [['group_by' => 'customer']],
]);

it('AC-002: the defaults and the normalised parameters are echoed in applied', function () {
    Sanctum::actingAs(mcActor());
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $default = mcGet('billing')->assertOk()->json('data');
    expect($default['applied'])->toBe([
        'date_from' => null, 'date_to' => null, 'company_id' => [], 'company_site_id' => [], 'operational_site_id' => [], 'group_by' => 'business_function',
    ])->and($default['today'])->toBe(mcDay(0))
        ->and($default['backlog'])->toMatchArray(['work_orders_count' => 0, 'contract' => 0, 'invoiced' => 0, 'to_invoice' => 0, 'by_dimension' => []])
        ->and(array_column($default['backlog']['by_age'], 'bucket'))->toBe(['0_3', '3_6', '6_12', 'over_12'])
        ->and(array_column($default['proforma']['open_by_age'], 'bucket'))->toBe(['0_30', '31_60', '61_90', 'over_90'])
        ->and(array_column($default['proforma']['by_kind'], 'kind'))->toBe(['consultancy', 'institution', null])
        ->and($default['proforma']['issued'])->toEqual(['documents' => 0, 'amount' => 0.0])
        ->and($default['proforma']['converted'])->toEqual(['documents' => 0, 'amount' => 0.0, 'average_days_to_invoice' => null])
        ->and($default['proforma']['average_days_invoice_to_collection'])->toBeNull();

    $applied = mcGet('billing', ['company_id' => [$second->id, $first->id, $second->id], 'group_by' => 'commercial', 'date_from' => '2026-01-01', 'date_to' => '2026-03-31'])
        ->assertOk()->json('data');

    expect($applied['applied'])->toMatchArray(['company_id' => [$first->id, $second->id], 'group_by' => 'commercial', 'date_from' => '2026-01-01', 'date_to' => '2026-03-31']);
});

it('AC-009: the number of queries does not depend on the commesse and documents', function () {
    Sanctum::actingAs(mcActor());
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $workOrder = mcWorkOrder(['start_date' => mcDay(-30 * ($i + 1))]);
            mcRevenueLine($workOrder, '100.00');
            mcInvoice($workOrder, InvoiceType::Invoice, '40.00', '40.00');
            $proforma = mcProforma('10.00', 10 + $i);
            mcConvert(mcProforma('20.00', 5 + $i), 2);
            mcCollect($proforma, '10.00', null);
        }
    };

    $seed(5);
    $few = mcQueryCount('billing', ['date_from' => mcDay(-400), 'date_to' => mcDay(0)]);
    $seed(20);

    expect(mcQueryCount('billing', ['date_from' => mcDay(-400), 'date_to' => mcDay(0)]))->toBe($few);
});
