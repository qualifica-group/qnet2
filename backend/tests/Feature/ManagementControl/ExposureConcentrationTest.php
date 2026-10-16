<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\CostType;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Spec 0269, AC-013 / AC-014 — the customer concentration and the constant query count.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

/** Four customers invoiced 400, 300, 200 and 100 (dated 10 days ago) with 50, 0, 150 and 0 overdue. */
function mcConcentrationScenario(): void
{
    [$one, $two, $three, $four] = collect(['Uno', 'Due', 'Tre', 'Quattro'])->map(fn (string $name): Registry => Registry::factory()->create(['name' => $name]))->all();
    $date = ['document_date' => mcDay(-10)];
    mcReceivable([['due' => -10, 'amount' => '50.00'], ['due' => 10, 'amount' => '350.00']], ['customer_registry_id' => $one->id, ...$date]);
    mcReceivable([['due' => 10, 'amount' => '300.00']], ['customer_registry_id' => $two->id, ...$date]);
    mcReceivable([['due' => -10, 'amount' => '150.00'], ['due' => 20, 'amount' => '50.00']], ['customer_registry_id' => $three->id, ...$date]);
    mcReceivable([['due' => 10, 'amount' => '100.00']], ['customer_registry_id' => $four->id, ...$date]);
}

it('AC-013: the first customers by invoiced amount with their shares of the invoiced and of the overdue', function () {
    mcConcentrationScenario();

    $concentration = mcGet('exposure', ['top' => 2])->assertOk()->json('data.concentration');

    expect($concentration)->toMatchArray(['invoiced_total' => 1000, 'overdue_total' => 200, 'top_invoiced_share' => 70.0, 'top_overdue_share' => 25.0])
        ->and(array_column(array_column($concentration['rows'], 'registry'), 'name'))->toBe(['Uno', 'Due'])
        ->and($concentration['rows'][0])->toMatchArray(['invoiced' => 400, 'invoiced_share' => 40.0, 'overdue' => 50, 'overdue_share' => 25.0])
        ->and($concentration['rows'][1])->toMatchArray(['invoiced' => 300, 'invoiced_share' => 30.0, 'overdue' => 0, 'overdue_share' => 0.0]);
});

it('AC-013: the period bounds the invoiced amount, the overdue is the snapshot at today', function () {
    mcConcentrationScenario();
    mcReceivable([['due' => -10, 'amount' => '999.00']], ['customer_registry_id' => Registry::factory()->create()->id, 'document_date' => mcDay(-300)]);

    $concentration = mcGet('exposure', ['top' => 2, 'date_from' => mcDay(-30), 'date_to' => mcDay(0)])->assertOk()->json('data.concentration');

    expect($concentration)->toMatchArray(['invoiced_total' => 1000, 'overdue_total' => 1199, 'top_invoiced_share' => 70.0, 'top_overdue_share' => 4.2])
        ->and($concentration['rows'][0])->toMatchArray(['overdue' => 50, 'overdue_share' => 4.2]);
});

it('AC-013: nothing invoiced gives empty rows and zero shares', function () {
    $concentration = mcGet('exposure')->assertOk()->json('data.concentration');

    expect($concentration)->toMatchArray(['invoiced_total' => 0, 'overdue_total' => 0, 'top_invoiced_share' => 0.0, 'top_overdue_share' => 0.0, 'rows' => []]);
});

it('AC-014: the number of queries does not grow with the number of documents', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        mcGet('exposure', ['top' => 25])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    $addDocuments = function (int $n): void {
        foreach (range(1, $n) as $_) {
            mcReceivable([['due' => -50, 'amount' => '100.00'], ['due' => 20, 'amount' => '50.00', 'collected' => '50.00']], ['customer_registry_id' => Registry::factory()->create()->id, 'document_date' => mcDay(-5)]);
            mcPayable([['due' => -10, 'amount' => '70.00'], ['due' => 15, 'amount' => '30.00', 'paid' => '30.00']], [
                'supplier_id' => Registry::factory()->supplier()->create()->id, 'cost_type_id' => CostType::factory()->create()->id, 'document_date' => mcDay(-5),
            ]);
            mcOrderLine(null, '20.00', PurchaseOrderStatus::Approved, ['supplier_id' => Registry::factory()->supplier()->create()->id]);
        }
    };

    $addDocuments(5);
    $count(); // warm-up: the actor's permissions load on the first request only
    $few = $count();
    $addDocuments(20);

    expect($count())->toBe($few);
});
