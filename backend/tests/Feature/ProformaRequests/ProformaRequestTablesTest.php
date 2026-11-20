<?php

use App\Models\PersonalData;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-006: proforma-requests rows carry the contract columns and filter by status; PATCH edits only the note; DELETE is 204', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view', 'update', 'delete']));
    $pending = ProformaRequest::factory()->create(['note' => 'open']);
    ProformaRequest::factory()->issued()->create(['note' => 'done']);

    $rows = $this->postJson('/api/tables/proforma-requests/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['pending']]],
    ])->assertOk()->json('items');

    expect($rows)->toHaveCount(1)
        ->and(array_keys($rows[0]))->toContain('work_order_code', 'work_order_title', 'company', 'kind', 'supplier', 'payment_method', 'status', 'note', 'assigned_to', 'assigned_by', 'created_at', 'actions')
        ->and($rows[0]['id'])->toBe($pending->id)
        ->and($rows[0]['actions'])->toEqualCanonicalizing(['view', 'update', 'notes', 'delete']);

    $this->postJson('/api/tables/proforma-requests/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'work_order_code', 'sort' => 'asc']],
    ])->assertOk();

    $this->patchJson("/api/proforma-requests/{$pending->id}", ['note' => 'edited', 'status' => 'issued', 'kind' => 'institution'])
        ->assertOk()->assertJsonPath('data.note', 'edited');
    expect($pending->fresh()->status->value)->toBe('pending')->and($pending->fresh()->kind->value)->toBe('consultancy');

    $this->deleteJson("/api/proforma-requests/{$pending->id}")->assertNoContent();
    expect(ProformaRequest::query()->count())->toBe(1);
});

it('exposes the work order customer (the quote opportunity registry) on the grid row and on the resource', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view']));
    $request = ProformaRequest::factory()->create();
    $customer = $request->workOrder->quote->opportunity->registry;
    $expected = ['id' => $customer->id, 'name' => $customer->name];

    $rows = $this->postJson('/api/tables/proforma-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    $columnIds = collect($this->getJson('/api/tables/proforma-requests/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($rows[0]['customer'])->toBe($expected)
        ->and($columnIds)->toContain('customer');
    // REQUIREMENT CHANGED 2026-11-12: the record also carries the "name - VAT number/tax code" label.
    $this->getJson("/api/proforma-requests/{$request->id}")->assertOk()->assertJsonPath('data.customer', $expected + ['label' => $customer->name]);
});

it('shows the customer VAT number (the registry personal-data card) as a visible column right after the customer', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view']));
    $withCard = ProformaRequest::factory()->create();
    PersonalData::factory()->for($withCard->workOrder->quote->opportunity->registry, 'personable')->create(['vat_number' => '00743110157']);
    $withoutCard = ProformaRequest::factory()->create();

    $rows = collect($this->postJson('/api/tables/proforma-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))->keyBy('id');
    $columns = collect($this->getJson('/api/tables/proforma-requests/columns')->assertOk()->json('data.columns'));
    $columnIds = $columns->pluck('id')->values();

    expect($rows[$withCard->id]['customer_vat_number'])->toBe('00743110157')
        ->and($rows[$withoutCard->id]['customer_vat_number'])->toBeNull()
        ->and($columnIds->search('customer_vat_number'))->toBe($columnIds->search('customer') + 1)
        ->and($columns->firstWhere('id', 'customer_vat_number')['visible'])->toBeTrue();
});
