<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $state
 * @return array<int, array<string, mixed>>
 */
function supplierGridRows(object $test, string $domain, array $state = []): array
{
    return $test->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50] + $state)->assertOk()->json('items');
}

/** A request whose lines carry the given suppliers (one line each, in order). */
function requestWithLineSuppliers(array $suppliers, array $attributes = []): PurchaseRequest
{
    $request = purchaseRequestWithLines(array_fill(0, count($suppliers), LineStatus::Approved), $attributes);

    foreach ($request->lines as $index => $line) {
        $line->update(['supplier_id' => $suppliers[$index]?->id]);
    }

    return $request->fresh('lines');
}

// ---------------------------------------------------------------------------
// AC-010 — RDA list: `suppliers`
// ---------------------------------------------------------------------------

it('AC-010: the RDA list shows the distinct line suppliers ordered by name, with no N+1', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $rossi = supplierRegistry();
    $rossi->update(['name' => 'Rossi Srl']);
    $bianchi = supplierRegistry();
    $bianchi->update(['name' => 'Bianchi Spa']);
    $request = requestWithLineSuppliers([$rossi, $bianchi, $rossi, null]);
    requestWithLineSuppliers([null]);

    $rows = collect(supplierGridRows($this, 'purchase-requests'))->keyBy('id');

    expect($rows[$request->id]['suppliers'])->toBe([['id' => $bianchi->id, 'name' => 'Bianchi Spa'], ['id' => $rossi->id, 'name' => 'Rossi Srl']])
        ->and($rows->except($request->id)->first()['suppliers'])->toBe([])
        ->and($rows[$request->id])->not->toHaveKey('supplier');
});

it('AC-010: the text filter keeps the requests with a line of a matching supplier', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $rossi = supplierRegistry();
    $rossi->update(['name' => 'Rossi Srl']);
    $other = supplierRegistry();
    $other->update(['name' => 'Verdi Spa']);
    $match = requestWithLineSuppliers([$other, $rossi]);
    requestWithLineSuppliers([$other]);
    requestWithLineSuppliers([null]);

    $text = supplierGridRows($this, 'purchase-requests', ['filterModel' => ['suppliers' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Rossi']]]);
    $set = supplierGridRows($this, 'purchase-requests', ['filterModel' => ['suppliers' => ['filterType' => 'set', 'values' => ['Rossi Srl']]]]);

    expect(array_column($text, 'id'))->toBe([$match->id])
        ->and(array_column($set, 'id'))->toBe([$match->id]);
});

it('AC-010: the quick search finds a request by the VAT number of a line supplier', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $supplier = supplierRegistry();
    $supplier->personalData()->create(['type' => 'company', 'company_name' => 'Forniture Spa', 'vat_number' => '01234567890']);
    $match = requestWithLineSuppliers([null, $supplier]);
    requestWithLineSuppliers([null]);

    expect(array_column(supplierGridRows($this, 'purchase-requests', ['search' => '0123456']), 'id'))->toBe([$match->id]);
});

it('AC-010: the suppliers column is not sortable and its values endpoint lists the distinct visible supplier names', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $rossi = supplierRegistry();
    $rossi->update(['name' => 'Rossi Srl']);
    $bianchi = supplierRegistry();
    $bianchi->update(['name' => 'Bianchi Spa']);
    supplierRegistry()->update(['name' => 'Unused Srl']);
    requestWithLineSuppliers([$rossi, $rossi, $bianchi]);

    $columns = collect($this->getJson('/api/tables/purchase-requests/columns')->assertOk()->json('data.columns'))->keyBy('id');
    expect($columns['suppliers']['sortable'])->toBeFalse()->and($columns->has('supplier'))->toBeFalse();

    $this->postJson('/api/tables/purchase-requests/values', ['columnId' => 'suppliers'])
        ->assertOk()
        ->assertJsonPath('data.values', ['Bianchi Spa', 'Rossi Srl']);
    $this->postJson('/api/tables/purchase-requests/values', ['columnId' => 'suppliers', 'search' => 'ross'])
        ->assertOk()
        ->assertJsonPath('data.values', ['Rossi Srl']);
});

// ---------------------------------------------------------------------------
// AC-011 — lines grid: `supplier`
// ---------------------------------------------------------------------------

it('AC-011: the lines grid filters and sorts by supplier and lists its distinct values', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $rossi = supplierRegistry();
    $rossi->update(['name' => 'Rossi Srl']);
    $bianchi = supplierRegistry();
    $bianchi->update(['name' => 'Bianchi Spa']);
    requestWithLineSuppliers([$rossi, $bianchi, null]);

    $description = fn (array $state) => array_column(supplierGridRows($this, 'purchase-request-lines', $state), 'supplier');
    $names = fn (array $rows) => array_map(fn (?array $supplier): ?string => $supplier['name'] ?? null, $rows);

    expect($names($description([])))->toContain('Rossi Srl', 'Bianchi Spa', null)
        ->and($names($description(['filterModel' => ['supplier' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Rossi']]])))->toBe(['Rossi Srl'])
        ->and($names($description(['filterModel' => ['supplier' => ['filterType' => 'set', 'values' => ['Bianchi Spa']]]])))->toBe(['Bianchi Spa'])
        ->and($names($description(['sortModel' => [['colId' => 'supplier', 'sort' => 'asc']]])))->toBe([null, 'Bianchi Spa', 'Rossi Srl'])
        ->and($names($description(['sortModel' => [['colId' => 'supplier', 'sort' => 'desc']]])))->toBe(['Rossi Srl', 'Bianchi Spa', null]);

    $this->postJson('/api/tables/purchase-request-lines/values', ['columnId' => 'supplier'])
        ->assertOk()
        ->assertJsonPath('data.values', ['Bianchi Spa', 'Rossi Srl']);

    expect(PurchaseRequestLine::query()->count())->toBe(3);
});
