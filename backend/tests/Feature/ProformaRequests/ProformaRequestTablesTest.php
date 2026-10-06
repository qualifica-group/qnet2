<?php

use App\Models\ProformaRequest;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-005: work-orders rows carry the "proforma" row action and proforma_status none/pending/issued only for actors who may create requests', function () {
    $none = WorkOrder::factory()->create();
    $pending = WorkOrder::factory()->create();
    $issued = WorkOrder::factory()->create();
    ProformaRequest::factory()->create(['work_order_id' => $pending->id]);
    ProformaRequest::factory()->issued()->create(['work_order_id' => $pending->id]);
    ProformaRequest::factory()->issued()->create(['work_order_id' => $issued->id]);
    $rows = fn () => collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    $actionKeys = fn () => collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.actions'))->pluck('key');
    $creator = proformaUserWith(['create']);
    $viewer = proformaUserWith(['view']);

    Sanctum::actingAs($creator);
    expect($rows()->pluck('proforma_status', 'id')->all())
        ->toEqualCanonicalizing([$none->id => 'none', $pending->id => 'pending', $issued->id => 'issued'])
        ->and($rows()->every(fn (array $row): bool => in_array('proforma', $row['actions'], true)))->toBeTrue()
        ->and($actionKeys())->toContain('proforma');

    Sanctum::actingAs($viewer);
    expect(array_filter($rows()->pluck('proforma_status', 'id')->all(), fn ($status) => $status !== null))->toBe([])
        ->and($rows()->contains(fn (array $row): bool => in_array('proforma', $row['actions'], true)))->toBeFalse()
        ->and($actionKeys())->not->toContain('proforma');
});

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
