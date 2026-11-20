<?php

use App\Models\ProductTypology;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * User directives 2026-11-17: the "Stato pagamento" column of the Commesse
 * grid groups the payment statuses of the commessa's products by typology
 * (Consulenza/Ente), and filters by typology + status pair.
 */

/**
 * `mixed`: a consultancy line paid, an institution line to pay, a consultancy
 * line without status; `twin`: two consultancy lines paid; `blank`: an
 * institution line without status.
 *
 * @return array{paid: WorkOrderPaymentStatus, toPay: WorkOrderPaymentStatus, consultancy: ProductTypology, institution: ProductTypology, mixed: WorkOrder, twin: WorkOrder, blank: WorkOrder}
 */
function linePaymentStatusFixture(): array
{
    $paid = WorkOrderPaymentStatus::factory()->create(['name' => 'Saldato', 'color' => 'green']);
    $toPay = WorkOrderPaymentStatus::factory()->create(['name' => 'Da pagare', 'color' => 'red']);
    $setStatus = static function (WorkOrder $workOrder, int $lineId, WorkOrderPaymentStatus $status): void {
        WorkOrderLinePayment::query()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $lineId, 'work_order_payment_status_id' => $status->id]);
    };

    $mixed = WorkOrder::factory()->create();
    $setStatus($mixed, contractDataLine($mixed, 'consultancy', 100)->id, $paid);
    $setStatus($mixed, contractDataLine($mixed, 'institution', 200)->id, $toPay);
    contractDataLine($mixed, 'consultancy', 50);

    $twin = WorkOrder::factory()->create();
    $setStatus($twin, contractDataLine($twin, 'consultancy', 100)->id, $paid);
    $setStatus($twin, contractDataLine($twin, 'consultancy', 100)->id, $paid);

    $blank = WorkOrder::factory()->create();
    contractDataLine($blank, 'institution', 100);

    return [
        'paid' => $paid,
        'toPay' => $toPay,
        'consultancy' => ProductTypology::query()->where('code', 'consultancy')->firstOrFail(),
        'institution' => ProductTypology::query()->where('code', 'institution')->firstOrFail(),
        'mixed' => $mixed,
        'twin' => $twin,
        'blank' => $blank,
    ];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<int, array<int, array<string, mixed>>>
 */
function linePaymentStatusRows(array $body = []): array
{
    return collect(test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, ...$body])
        ->assertOk()
        ->json('items'))
        ->pluck('line_payment_statuses', 'id')
        ->all();
}

it('rows group the products payment statuses by typology, a product without status as no status', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $fixture = linePaymentStatusFixture();
    $noStatus = __('work_orders.line_payment_statuses.no_status');
    $consultancy = $fixture['consultancy'];

    $rows = linePaymentStatusRows();
    $mixed = $rows[$fixture['mixed']->id];

    expect($mixed)->toHaveCount(2)
        ->and($mixed[0]['name'])->toBe("{$consultancy->name}: Saldato, {$noStatus}")
        ->and($mixed[0]['typology'])->toBe(['name' => $consultancy->name, 'color' => $consultancy->color])
        ->and($mixed[0]['statuses'])->toBe([['name' => 'Saldato', 'color' => 'green'], null])
        ->and(array_column($mixed[0]['products'], 'status'))->toBe(['Saldato', null])
        ->and($mixed[1]['name'])->toBe("{$fixture['institution']->name}: Da pagare")
        ->and($rows[$fixture['twin']->id][0]['statuses'])->toBe([['name' => 'Saldato', 'color' => 'green']])
        ->and($rows[$fixture['twin']->id][0]['products'])->toHaveCount(2)
        ->and($rows[$fixture['blank']->id][0]['statuses'])->toBe([null]);
});

it('declares a set-filterable, not sortable badge column labelling every typology + status pair', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $fixture = linePaymentStatusFixture();

    $column = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'line_payment_statuses');
    $labels = collect($column['badges'])->pluck('label', 'value');

    expect($column['label'])->toBe('workOrders.columns.line_payment_statuses')
        ->and($column['type'])->toBe('badge')
        ->and($column['sortable'])->toBeFalse()
        ->and($column['filterType'])->toBe('set')
        ->and($labels["{$fixture['consultancy']->id}-{$fixture['paid']->id}"])->toBe("{$fixture['consultancy']->name}: Saldato")
        ->and($labels["{$fixture['institution']->id}-none"])->toBe("{$fixture['institution']->name}: ".__('work_orders.line_payment_statuses.no_status'));
});

it('the set filter keeps the commesse with a product in a picked typology + status pair', function (array $picked, array $expected) {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $fixture = linePaymentStatusFixture();
    $keys = [
        'consultancy paid' => "{$fixture['consultancy']->id}-{$fixture['paid']->id}",
        'institution to pay' => "{$fixture['institution']->id}-{$fixture['toPay']->id}",
        'institution paid' => "{$fixture['institution']->id}-{$fixture['paid']->id}",
        'consultancy none' => "{$fixture['consultancy']->id}-none",
        'institution none' => "{$fixture['institution']->id}-none",
        'garbage' => '1; DROP TABLE work_orders',
    ];

    $rows = linePaymentStatusRows(['filterModel' => ['line_payment_statuses' => [
        'filterType' => 'set',
        'values' => array_map(static fn (string $pick): string => $keys[$pick], $picked),
    ]]]);

    expect(array_keys($rows))->toEqualCanonicalizing(array_map(static fn (string $key): int => $fixture[$key]->id, $expected));
})->with([
    'consultancy paid' => [['consultancy paid'], ['mixed', 'twin']],
    'institution to pay' => [['institution to pay'], ['mixed']],
    'institution paid' => [['institution paid'], []],
    'consultancy without status' => [['consultancy none'], ['mixed']],
    'institution without status' => [['institution none'], ['blank']],
    'either' => [['institution to pay', 'institution none'], ['mixed', 'blank']],
    'only garbage: no constraint' => [['garbage'], ['mixed', 'twin', 'blank']],
]);

it('the filter offers the pairs in use, by typology then status order, no status last', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $fixture = linePaymentStatusFixture();
    WorkOrderPaymentStatus::factory()->create(['name' => 'Mai usato']);
    $byTypology = [
        $fixture['consultancy']->name => ["{$fixture['consultancy']->id}-{$fixture['paid']->id}", "{$fixture['consultancy']->id}-none"],
        $fixture['institution']->name => ["{$fixture['institution']->id}-{$fixture['toPay']->id}", "{$fixture['institution']->id}-none"],
    ];
    ksort($byTypology);

    $this->postJson('/api/tables/work-orders/values', ['columnId' => 'line_payment_statuses', 'limit' => 25])
        ->assertOk()
        ->assertJsonPath('data.values', array_merge(...array_values($byTypology)));
});

it('the filter search matches the pair labels', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $fixture = linePaymentStatusFixture();

    $this->postJson('/api/tables/work-orders/values', ['columnId' => 'line_payment_statuses', 'search' => 'pagare', 'limit' => 25])
        ->assertOk()
        ->assertJsonPath('data.values', ["{$fixture['institution']->id}-{$fixture['toPay']->id}"]);
});
