<?php

use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Work order payment statuses lookup (spec 0201): AC-012 (seeder) and AC-013
 * (CRUD / for-select / reorder / table).
 */
uses(RefreshDatabase::class);

it('AC-012: the reference seeder creates the 10 legacy statuses, delivery only for 1,2,3,6, idempotent', function () {
    $this->seed(WorkOrderPaymentStatusSeeder::class);
    $this->seed(WorkOrderPaymentStatusSeeder::class);

    expect(WorkOrderPaymentStatus::count())->toBe(10);

    $deliverable = WorkOrderPaymentStatus::where('allows_delivery', true)->orderBy('old_id')->pluck('old_id')->all();
    expect($deliverable)->toBe([1, 2, 3, 6])
        ->and(WorkOrderPaymentStatus::orderBy('old_id')->pluck('old_id')->all())->toBe(range(1, 10));
});

it('AC-012: the seeded names keep the legacy wording', function () {
    $this->seed(WorkOrderPaymentStatusSeeder::class);

    expect(WorkOrderPaymentStatus::where('old_id', 3)->value('name'))->toBe('Verde (Saldato, si può consegnare)');
});

it('AC-013: create returns 201 with the frozen resource shape, server sort_order, old_id ignored', function () {
    Sanctum::actingAs(workOrderPaymentStatusUserWith(['create']));

    $this->postJson('/api/work-order-payment-statuses', [
        'name' => 'Pagato', 'color' => 'green', 'allows_delivery' => true, 'sort_order' => 999, 'old_id' => 77,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Pagato')
        ->assertJsonPath('data.allows_delivery', true)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'color', 'sort_order', 'is_active', 'allows_delivery', 'created_at', 'updated_at']])
        ->assertJsonMissingPath('data.old_id');

    $status = WorkOrderPaymentStatus::firstWhere('name', 'Pagato');
    expect($status->sort_order)->not->toBe(999)->and($status->old_id)->toBeNull();
});

it('AC-013: create is 403 without permission and 422 on missing or duplicate fields', function () {
    Sanctum::actingAs(workOrderPaymentStatusUserWith([]));
    $this->postJson('/api/work-order-payment-statuses', ['name' => 'X', 'color' => 'red'])->assertForbidden();

    WorkOrderPaymentStatus::factory()->create(['name' => 'Taken']);
    Sanctum::actingAs(workOrderPaymentStatusUserWith(['create']));

    $this->postJson('/api/work-order-payment-statuses', ['name' => 'Taken', 'color' => 'red'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/work-order-payment-statuses', ['name' => 'No color'])->assertUnprocessable()->assertJsonValidationErrors('color');
    $this->postJson('/api/work-order-payment-statuses', ['name' => 'Bad flag', 'color' => 'red', 'allows_delivery' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('allows_delivery');
});

it('AC-013: show and update (PATCH + PUT) work with permission, 403 without', function () {
    $status = WorkOrderPaymentStatus::factory()->create(['name' => 'Old', 'allows_delivery' => false]);

    Sanctum::actingAs(workOrderPaymentStatusUserWith([]));
    $this->getJson("/api/work-order-payment-statuses/{$status->id}")->assertForbidden();
    $this->patchJson("/api/work-order-payment-statuses/{$status->id}", ['name' => 'New'])->assertForbidden();

    Sanctum::actingAs(workOrderPaymentStatusUserWith(['view', 'update']));
    $this->getJson("/api/work-order-payment-statuses/{$status->id}")->assertOk()->assertJsonPath('data.name', 'Old');
    $this->patchJson("/api/work-order-payment-statuses/{$status->id}", ['allows_delivery' => true])
        ->assertOk()->assertJsonPath('data.allows_delivery', true)->assertJsonPath('data.name', 'Old');
    $this->putJson("/api/work-order-payment-statuses/{$status->id}", ['name' => 'New'])->assertOk()->assertJsonPath('data.name', 'New');
    $this->patchJson("/api/work-order-payment-statuses/{$status->id}", ['color' => ''])->assertUnprocessable();
});

it('AC-013: delete is 204, 403 without permission, 409 while a line payment uses the status', function () {
    $free = WorkOrderPaymentStatus::factory()->create();
    $used = WorkOrderPaymentStatus::factory()->create();
    WorkOrderLinePayment::factory()->create(['work_order_payment_status_id' => $used->id]);

    Sanctum::actingAs(workOrderPaymentStatusUserWith([]));
    $this->deleteJson("/api/work-order-payment-statuses/{$free->id}")->assertForbidden();

    Sanctum::actingAs(workOrderPaymentStatusUserWith(['delete']));
    $this->deleteJson("/api/work-order-payment-statuses/{$used->id}")->assertStatus(409);
    $this->deleteJson("/api/work-order-payment-statuses/{$free->id}")->assertNoContent();

    expect(WorkOrderPaymentStatus::find($used->id))->not->toBeNull()
        ->and(WorkOrderPaymentStatus::find($free->id))->toBeNull();
});

it('AC-013: for-select returns only active rows with name/color/allows_delivery, requires auth', function () {
    $this->getJson('/api/work-order-payment-statuses/for-select')->assertUnauthorized();

    $active = WorkOrderPaymentStatus::factory()->deliverable()->create(['name' => 'Active one', 'color' => 'blue']);
    $inactive = WorkOrderPaymentStatus::factory()->create(['name' => 'Inactive one', 'is_active' => false]);
    Sanctum::actingAs(workOrderPaymentStatusUserWith([]));

    $response = $this->getJson('/api/work-order-payment-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure(['items' => [['id', 'name', 'color', 'allows_delivery']], 'pagination']);

    $ids = collect($response->json('items'))->pluck('id')->all();
    expect($ids)->toContain($active->id)->not->toContain($inactive->id);
    expect(collect($response->json('items'))->firstWhere('id', $active->id))
        ->toMatchArray(['name' => 'Active one', 'color' => 'blue', 'allows_delivery' => true]);

    $hydrated = $this->getJson("/api/work-order-payment-statuses/for-select?ids[]={$inactive->id}")->assertOk()->json('items');
    expect(collect($hydrated)->pluck('id')->all())->toContain($inactive->id);
});

it('AC-013: reorder resequences all rows, 403 without update, 422 on an incomplete set', function () {
    $a = WorkOrderPaymentStatus::factory()->create();
    $b = WorkOrderPaymentStatus::factory()->create();

    Sanctum::actingAs(workOrderPaymentStatusUserWith([]));
    $this->postJson('/api/work-order-payment-statuses/reorder', ['ordered_ids' => [$b->id, $a->id]])->assertForbidden();

    Sanctum::actingAs(workOrderPaymentStatusUserWith(['update']));
    $this->postJson('/api/work-order-payment-statuses/reorder', ['ordered_ids' => [$b->id]])->assertUnprocessable();
    $this->postJson('/api/work-order-payment-statuses/reorder', ['ordered_ids' => [$b->id, $a->id]])->assertOk();

    expect($b->fresh()->sort_order)->toBe(10)->and($a->fresh()->sort_order)->toBe(20);
});

it('AC-013: the table endpoint is registered, 403 without viewAny, rows carry allows_delivery', function () {
    $status = WorkOrderPaymentStatus::factory()->deliverable()->create();

    Sanctum::actingAs(workOrderPaymentStatusUserWith([]));
    $this->getJson('/api/tables/work-order-payment-statuses/columns')->assertForbidden();

    Sanctum::actingAs(workOrderPaymentStatusUserWith(['viewAny']));
    $this->getJson('/api/tables/work-order-payment-statuses/columns')->assertOk()
        ->assertJsonPath('data.resource', 'work-order-payment-statuses');

    $rows = $this->postJson('/api/tables/work-order-payment-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    expect(collect($rows)->firstWhere('id', $status->id))->toMatchArray(['allows_delivery' => true, 'color' => $status->color]);
});
