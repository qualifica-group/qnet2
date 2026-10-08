<?php

use App\Models\CompanySite;
use App\Models\Registry;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function groupRows(array $body): array
{
    return test()->postJson('/api/tables/invoice-installments/rows', $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
}

beforeEach(function () {
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view']));
});

it('AC-005: groups by customer with the child count, the sums and the number of groups as total', function () {
    $acme = Registry::factory()->create(['name' => 'Acme']);
    $beta = Registry::factory()->create(['name' => 'Beta']);
    $invoice = installmentInvoice($acme);
    installmentOf($invoice, ['amount' => '100.00', 'collected_amount' => '40.00']);
    installmentOf($invoice, ['amount' => '50.00']);
    installmentOf(installmentInvoice($beta), ['amount' => '10.00']);

    $body = groupRows(['rowGroupCols' => ['customer'], 'groupKeys' => []]);

    expect($body['items'])->toBe([
        ['group' => true, 'column' => 'customer', 'key' => (string) $acme->id, 'label' => 'Acme', 'child_count' => 2,
            'aggregates' => ['amount' => '150.00', 'collected_amount' => '40.00', 'residual_amount' => '110.00']],
        ['group' => true, 'column' => 'customer', 'key' => (string) $beta->id, 'label' => 'Beta', 'child_count' => 1,
            'aggregates' => ['amount' => '10.00', 'collected_amount' => '0.00', 'residual_amount' => '10.00']],
    ])
        ->and($body['pagination']['total'])->toBe(2)
        ->and($body['meta']['aggregates'])->toBe(['amount' => '160.00', 'collected_amount' => '40.00', 'residual_amount' => '120.00']);
});

it('AC-005: groups sort by label asc by default and by the grouped or an aggregate column on request, and paginate', function () {
    $names = ['Cc' => '5.00', 'Aa' => '30.00', 'Bb' => '20.00'];

    foreach ($names as $name => $amount) {
        installmentOf(installmentInvoice(Registry::factory()->create(['name' => $name])), ['amount' => $amount]);
    }

    $labels = static fn (array $body): array => array_column(groupRows(['rowGroupCols' => ['customer']] + $body)['items'], 'label');

    expect($labels([]))->toBe(['Aa', 'Bb', 'Cc'])
        ->and($labels(['sortModel' => [['colId' => 'customer', 'sort' => 'desc']]]))->toBe(['Cc', 'Bb', 'Aa'])
        ->and($labels(['sortModel' => [['colId' => 'amount', 'sort' => 'desc']]]))->toBe(['Aa', 'Bb', 'Cc'])
        ->and($labels(['sortModel' => [['colId' => 'amount', 'sort' => 'asc']]]))->toBe(['Cc', 'Bb', 'Aa'])
        ->and($labels(['sortModel' => [['colId' => 'due_date', 'sort' => 'desc']]]))->toBe(['Aa', 'Bb', 'Cc'])
        ->and($labels(['startRow' => 1, 'endRow' => 3]))->toBe(['Bb', 'Cc'])
        ->and(groupRows(['rowGroupCols' => ['customer'], 'startRow' => 2, 'endRow' => 3, 'knownTotal' => 3])['pagination']['total'])->toBe(3);
});

it('AC-006: opens a second level under a parent key and the leaf rows under all keys', function () {
    $acme = Registry::factory()->create(['name' => 'Acme']);
    $other = Registry::factory()->create(['name' => 'Other']);
    $wo1 = installmentInvoice($acme);
    $wo2 = installmentInvoice($acme);
    $foreign = installmentInvoice($other);
    $a = installmentOf($wo1);
    $b = installmentOf($wo2);
    installmentOf($foreign);

    $level2 = groupRows(['rowGroupCols' => ['customer', 'work_order'], 'groupKeys' => [(string) $acme->id]]);

    expect(array_column($level2['items'], 'key'))->toEqualCanonicalizing([(string) $wo1->work_order_id, (string) $wo2->work_order_id])
        ->and($level2['items'][0]['column'])->toBe('work_order')
        ->and($level2['items'][0]['label'])->toContain(WorkOrder::query()->find($level2['items'][0]['key'])->code.' - ')
        ->and($level2['pagination']['total'])->toBe(2);

    $leaf = groupRows(['rowGroupCols' => ['customer', 'work_order'], 'groupKeys' => [(string) $acme->id, (string) $wo2->work_order_id]]);

    expect(array_column($leaf['items'], 'id'))->toBe([$b->id])
        ->and($leaf['items'][0])->not->toHaveKey('group')
        ->and($leaf['items'][0])->toHaveKey('actions')
        ->and($leaf['pagination']['total'])->toBe(1)
        ->and(array_column(groupRows(['rowGroupCols' => ['customer'], 'groupKeys' => [(string) $acme->id]])['items'], 'id'))->toEqualCanonicalizing([$a->id, $b->id]);
});

it('AC-007: rows with no site fall in the __null__ group and the key returns them', function () {
    $site = CompanySite::factory()->create(['name' => 'HQ']);
    $withSite = installmentOf(installmentInvoice(null, $site));
    $without = installmentOf(installmentInvoice());
    $withoutAlso = installmentOf(installmentInvoice());

    $groups = collect(groupRows(['rowGroupCols' => ['company_site']])['items'])->keyBy('key');

    expect($groups->keys()->all())->toEqualCanonicalizing([(string) $site->id, '__null__'])
        ->and($groups['__null__'])->toMatchArray(['child_count' => 2, 'label' => null, 'column' => 'company_site'])
        ->and($groups[(string) $site->id]['label'])->toBe('HQ');

    $leaf = groupRows(['rowGroupCols' => ['company_site'], 'groupKeys' => ['__null__']]);

    expect(array_column($leaf['items'], 'id'))->toEqualCanonicalizing([$without->id, $withoutAlso->id])->not->toContain($withSite->id);
});

it('groups by the plain-value columns: payment method code, due month, operational site and company', function () {
    $invoice = installmentInvoice();
    installmentOf($invoice, ['payment_method_code' => 'RB30', 'due_date' => '2026-04-10']);
    installmentOf($invoice, ['payment_method_code' => 'RB30', 'due_date' => '2026-04-25']);
    installmentOf($invoice, ['payment_method_code' => null, 'due_date' => '2026-05-02']);

    $byCode = collect(groupRows(['rowGroupCols' => ['payment_method_code']])['items'])->keyBy('key');
    $byMonth = collect(groupRows(['rowGroupCols' => ['due_month']])['items'])->keyBy('key');

    expect($byCode['RB30']['child_count'])->toBe(2)
        ->and($byCode['__null__']['child_count'])->toBe(1)
        ->and($byMonth->keys()->all())->toBe(['2026-04', '2026-05'])
        ->and(array_column(groupRows(['rowGroupCols' => ['due_month'], 'groupKeys' => ['2026-04']])['items'], 'id'))->toHaveCount(2)
        ->and(groupRows(['rowGroupCols' => ['due_month', 'payment_method_code'], 'groupKeys' => ['2026-04']])['items'][0])
        ->toMatchArray(['column' => 'payment_method_code', 'key' => 'RB30', 'child_count' => 2])
        ->and(groupRows(['rowGroupCols' => ['operational_site']])['items'][0]['key'])->toBe('__null__')
        ->and(groupRows(['rowGroupCols' => ['company']])['items'][0]['child_count'])->toBe(3);
});

it('AC-008: active filters apply to the groups and to their aggregates', function () {
    $acme = Registry::factory()->create(['name' => 'Acme']);
    $invoice = installmentInvoice($acme);
    installmentOf($invoice, ['amount' => '100.00', 'due_date' => Carbon::today()->subDays(2)->toDateString()]);
    installmentOf($invoice, ['amount' => '40.00', 'due_date' => Carbon::today()->addDays(9)->toDateString()]);
    installmentOf($invoice, ['amount' => '25.00', 'due_date' => Carbon::today()->subDays(9)->toDateString(), 'collected_amount' => '25.00']);
    installmentOf(installmentInvoice(Registry::factory()->create(['name' => 'Beta'])), ['amount' => '7.00', 'due_date' => Carbon::today()->addDay()->toDateString()]);

    $open = groupRows(['rowGroupCols' => ['customer'], 'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['unpaid']]]]);
    $overdue = groupRows(['rowGroupCols' => ['customer'], 'filterModel' => ['overdue' => ['filterType' => 'set', 'values' => ['yes']]]]);

    expect($open['items'][0])->toMatchArray(['label' => 'Acme', 'child_count' => 2])
        ->and($open['items'][0]['aggregates']['amount'])->toBe('140.00')
        ->and($open['meta']['aggregates']['amount'])->toBe('147.00')
        ->and($overdue['pagination']['total'])->toBe(1)
        ->and($overdue['items'][0]['aggregates']['amount'])->toBe('100.00')
        ->and($overdue['meta']['aggregates']['residual_amount'])->toBe('100.00');
});

it('AC-009: invalid grouping requests are 422', function (array $body) {
    $this->postJson('/api/tables/invoice-installments/rows', $body + ['startRow' => 0, 'endRow' => 10])->assertUnprocessable();
})->with([
    'column not groupable' => [['rowGroupCols' => ['invoice_number_label']]],
    'unknown column' => [['rowGroupCols' => ['password']]],
    'more than 3 levels' => [['rowGroupCols' => ['customer', 'work_order', 'company', 'due_month']]],
    'repeated column' => [['rowGroupCols' => ['customer', 'customer']]],
    'more keys than columns' => [['rowGroupCols' => ['customer'], 'groupKeys' => ['1', '2']]],
    'keys without columns' => [['groupKeys' => ['1']]],
    'key too long' => [['rowGroupCols' => ['customer'], 'groupKeys' => [str_repeat('x', 192)]]],
    'combined with tree' => [['rowGroupCols' => ['customer'], 'tree' => true]],
    'combined with kanban' => [['rowGroupCols' => ['customer'], 'kanbanGroup' => ['by' => 'due', 'key' => 'today']]],
]);

it('AC-009: grouping on a domain without the opt-in is a 422, an empty rowGroupCols is harmless', function () {
    Sanctum::actingAs(installmentUserWith([], ['invoices.viewAny']));

    $this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['customer']])
        ->assertUnprocessable()->assertJsonValidationErrors(['rowGroupCols']);
    $this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => [], 'groupKeys' => []])->assertOk();

    $config = $this->getJson('/api/tables/invoices/columns')->assertOk()->json('data');
    expect($config)->not->toHaveKey('row_grouping')
        ->and($config['columns'][1])->not->toHaveKeys(['groupable', 'aggFunc']);
});
