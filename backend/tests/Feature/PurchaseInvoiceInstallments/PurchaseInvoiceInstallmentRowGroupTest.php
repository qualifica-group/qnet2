<?php

use App\Models\CostType;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function purchaseGroupRows(array $body): array
{
    return test()->postJson('/api/tables/purchase-invoice-installments/rows', $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
}

beforeEach(function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view']));
});

it('AC-005: groups by supplier with the child count, the sums and the number of groups as total', function () {
    $acme = Registry::factory()->supplier()->create(['name' => 'Acme']);
    $beta = Registry::factory()->supplier()->create(['name' => 'Beta']);
    $document = purchaseDocument($acme);
    purchaseInstallmentOf($document, ['amount' => '100.00', 'paid_amount' => '40.00']);
    purchaseInstallmentOf($document, ['amount' => '50.00']);
    purchaseInstallmentOf(purchaseDocument($beta), ['amount' => '10.00']);

    $body = purchaseGroupRows(['rowGroupCols' => ['supplier'], 'groupKeys' => []]);

    expect($body['items'])->toBe([
        ['group' => true, 'column' => 'supplier', 'key' => (string) $acme->id, 'label' => 'Acme', 'child_count' => 2,
            'aggregates' => ['amount' => '150.00', 'paid_amount' => '40.00', 'residual_amount' => '110.00']],
        ['group' => true, 'column' => 'supplier', 'key' => (string) $beta->id, 'label' => 'Beta', 'child_count' => 1,
            'aggregates' => ['amount' => '10.00', 'paid_amount' => '0.00', 'residual_amount' => '10.00']],
    ])
        ->and($body['pagination']['total'])->toBe(2)
        ->and($body['meta']['aggregates'])->toBe(['amount' => '160.00', 'paid_amount' => '40.00', 'residual_amount' => '120.00']);
});

it('AC-005: opens supplier then cost type, sends missing values to __null__ and returns the leaves under all keys', function () {
    $acme = Registry::factory()->supplier()->create(['name' => 'Acme']);
    $costType = CostType::query()->orderBy('id')->first();
    $withType = purchaseInstallmentOf(purchaseDocument($acme, ['cost_type_id' => $costType->id]));
    $withoutType = purchaseInstallmentOf(purchaseDocument($acme, ['cost_type_id' => null]));
    purchaseInstallmentOf(purchaseDocument());

    $level2 = collect(purchaseGroupRows(['rowGroupCols' => ['supplier', 'cost_type'], 'groupKeys' => [(string) $acme->id]])['items'])->keyBy('key');

    expect($level2->keys()->all())->toEqualCanonicalizing([(string) $costType->id, '__null__'])
        ->and($level2[(string) $costType->id])->toMatchArray(['column' => 'cost_type', 'label' => $costType->name, 'child_count' => 1])
        ->and($level2['__null__'])->toMatchArray(['label' => null, 'child_count' => 1]);

    $leaf = purchaseGroupRows(['rowGroupCols' => ['supplier', 'cost_type'], 'groupKeys' => [(string) $acme->id, '__null__']]);

    expect(array_column($leaf['items'], 'id'))->toBe([$withoutType->id])->not->toContain($withType->id)
        ->and($leaf['items'][0])->toHaveKey('actions')->not->toHaveKey('group');
});

it('AC-005: active filters apply to the groups and their aggregates', function () {
    $acme = Registry::factory()->supplier()->create(['name' => 'Acme']);
    $document = purchaseDocument($acme);
    purchaseInstallmentOf($document, ['amount' => '100.00']);
    purchaseInstallmentOf($document, ['amount' => '25.00', 'paid_amount' => '25.00']);
    purchaseInstallmentOf(purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Beta'])), ['amount' => '7.00', 'paid_amount' => '7.00']);

    $open = purchaseGroupRows(['rowGroupCols' => ['supplier'], 'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['unpaid']]]]);

    expect($open['pagination']['total'])->toBe(1)
        ->and($open['items'][0])->toMatchArray(['label' => 'Acme', 'child_count' => 1])
        ->and($open['items'][0]['aggregates']['amount'])->toBe('100.00')
        ->and($open['meta']['aggregates']['amount'])->toBe('100.00');
});

it('AC-005: groups by the plain-value columns', function () {
    $document = purchaseDocument();
    purchaseInstallmentOf($document, ['payment_method_code' => 'RB30', 'due_date' => '2026-04-10']);
    purchaseInstallmentOf($document, ['payment_method_code' => null, 'due_date' => '2026-05-02']);

    $byCode = collect(purchaseGroupRows(['rowGroupCols' => ['payment_method_code']])['items'])->keyBy('key');
    $byMonth = collect(purchaseGroupRows(['rowGroupCols' => ['due_month']])['items'])->keyBy('key');

    expect($byCode['RB30']['child_count'])->toBe(1)->and($byCode['__null__']['child_count'])->toBe(1)
        ->and($byMonth->keys()->all())->toBe(['2026-04', '2026-05'])
        ->and(purchaseGroupRows(['rowGroupCols' => ['company']])['items'][0]['child_count'])->toBe(2)
        ->and(purchaseGroupRows(['rowGroupCols' => ['financial_account']])['items'][0]['key'])->toBe('__null__');
});

it('AC-005: invalid grouping requests are 422', function (array $body) {
    $this->postJson('/api/tables/purchase-invoice-installments/rows', $body + ['startRow' => 0, 'endRow' => 10])->assertUnprocessable();
})->with([
    'column not groupable' => [['rowGroupCols' => ['document_number']]],
    'unknown column' => [['rowGroupCols' => ['password']]],
    'more than 3 levels' => [['rowGroupCols' => ['supplier', 'work_order', 'company', 'due_month']]],
    'repeated column' => [['rowGroupCols' => ['supplier', 'supplier']]],
    'more keys than columns' => [['rowGroupCols' => ['supplier'], 'groupKeys' => ['1', '2']]],
]);
