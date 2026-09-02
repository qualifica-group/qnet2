<?php

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// numbering — POST /api/work-orders (AC-010..012), GET /next-code (AC-013)
// ---------------------------------------------------------------------------

it('create: two consecutive creates without code get COM-0001 then COM-0002 (AC-010)', function () {
    $actor = workOrderUserWith(['create']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $first = $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'Prima', 'type' => 'processing'])
        ->assertCreated();
    $second = $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'Seconda', 'type' => 'project'])
        ->assertCreated();

    expect($first->json('data.code'))->toBe('COM-0001')
        ->and($second->json('data.code'))->toBe('COM-0002');
});

it('create: 201 with a manual code persists it verbatim, a duplicate code 422s (AC-011)', function () {
    $actor = workOrderUserWith(['create']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'Manuale', 'type' => 'processing', 'code' => 'COM-9999'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'COM-9999');

    $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'Duplicata', 'type' => 'processing', 'code' => 'COM-9999'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('update: PATCH with code is 422 (prohibited), code unchanged at DB (AC-012)', function () {
    $actor = workOrderUserWith(['update']);
    $workOrder = WorkOrder::factory()->create(['code' => 'COM-0010']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['code' => 'COM-0099'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'code' => 'COM-0010']);
});

it('GET /api/work-orders/next-code previews without consuming: two calls return the same value (AC-013)', function () {
    $actor = workOrderUserWith(['create']);
    Sanctum::actingAs($actor);

    $first = $this->getJson('/api/work-orders/next-code')->assertOk()->json('data.code');
    $second = $this->getJson('/api/work-orders/next-code')->assertOk()->json('data.code');

    expect($first)->toBe($second)->toBe('COM-0001');
});

// ---------------------------------------------------------------------------
// quote / lines — AC-020..027
// ---------------------------------------------------------------------------

it('create: 422 when quote_id does not exist (AC-020)', function () {
    $actor = workOrderUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', ['quote_id' => 999999, 'title' => 'Nope', 'type' => 'processing'])
        ->assertStatus(422)->assertJsonValidationErrors('quote_id');
});

it('update: PATCH with quote_id is 422 (prohibited), quote unchanged at DB (AC-021)', function () {
    $actor = workOrderUserWith(['update']);
    $workOrder = WorkOrder::factory()->create();
    $otherQuote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_id' => $otherQuote->id])
        ->assertStatus(422)->assertJsonValidationErrors('quote_id');

    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'quote_id' => $workOrder->quote_id]);
});

it('create: 422 when a quote_line_id belongs to ANOTHER offer, no work order created (AC-022)', function () {
    $actor = workOrderUserWith(['create']);
    $quote = Quote::factory()->create();
    $otherQuote = Quote::factory()->create();
    $foreignLine = QuoteLine::factory()->create(['quote_id' => $otherQuote->id]);
    Sanctum::actingAs($actor);

    $countBefore = WorkOrder::count();

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Bad lines', 'type' => 'processing',
        'quote_line_ids' => [$foreignLine->id],
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect(WorkOrder::count())->toBe($countBefore);
});

it('create: 422 when a quote_line_id is a COST line of the SAME offer (AC-023)', function () {
    $actor = workOrderUserWith(['create']);
    $quote = Quote::factory()->create();
    $costLine = QuoteLine::factory()->cost()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Cost line', 'type' => 'processing',
        'quote_line_ids' => [$costLine->id],
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect(WorkOrder::count())->toBe(0);
});

it('create: 201 with multiple valid REVENUE lines persists one pivot row each (AC-024)', function () {
    $actor = workOrderUserWith(['create']);
    $quote = Quote::factory()->create();
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id, 'sort_order' => 1]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id, 'sort_order' => 2]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Multi-line', 'type' => 'processing',
        'quote_line_ids' => [$lineA->id, $lineB->id],
    ])->assertCreated();

    $workOrderId = $response->json('data.id');
    $this->assertDatabaseHas('quote_line_work_order', ['work_order_id' => $workOrderId, 'quote_line_id' => $lineA->id]);
    $this->assertDatabaseHas('quote_line_work_order', ['work_order_id' => $workOrderId, 'quote_line_id' => $lineB->id]);
    expect($response->json('data.quote_lines'))->toHaveCount(2);
});

it('update: PATCH quote_line_ids [B,C] replaces [A,B] leaving exactly B and C (AC-025)', function () {
    $actor = workOrderUserWith(['update']);
    $quote = Quote::factory()->create();
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $lineC = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach([$lineA->id, $lineB->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$lineB->id, $lineC->id]])
        ->assertOk();

    $ids = $workOrder->quoteLines()->pluck('quote_lines.id')->sort()->values()->all();
    expect($ids)->toBe(collect([$lineB->id, $lineC->id])->sort()->values()->all());
});

it('update: PATCH without quote_line_ids leaves the linked lines untouched (AC-026)', function () {
    $actor = workOrderUserWith(['update']);
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id, 'title' => 'Before']);
    $workOrder->quoteLines()->attach($line->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'After'])->assertOk();

    expect($workOrder->quoteLines()->pluck('quote_lines.id')->all())->toBe([$line->id]);
});

// ---------------------------------------------------------------------------
// force close + computed status — AC-030..034 (AC-034 table coverage lives
// in WorkOrderTableTest)
// ---------------------------------------------------------------------------

it('create: 422 when is_force_closed=true and force_close_reason is absent/empty (AC-030)', function () {
    $actor = workOrderUserWith(['create']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'No reason', 'type' => 'processing', 'is_force_closed' => true])
        ->assertStatus(422)->assertJsonValidationErrors('force_close_reason');

    $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'Empty reason', 'type' => 'processing', 'is_force_closed' => true, 'force_close_reason' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('force_close_reason');
});

it('update: is_force_closed true->false zeroes force_close_reason in the same save (AC-031)', function () {
    $actor = workOrderUserWith(['update']);
    $workOrder = WorkOrder::factory()->forceClosed()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => false])
        ->assertOk()
        ->assertJsonPath('data.is_force_closed', false)
        ->assertJsonPath('data.force_close_reason', null);

    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'is_force_closed' => false, 'force_close_reason' => null]);
});

it('status.value is open when is_force_closed=false, closed when true (AC-032)', function () {
    $actor = workOrderUserWith(['view']);
    $open = WorkOrder::factory()->create(['is_force_closed' => false]);
    $closed = WorkOrder::factory()->forceClosed()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$open->id}")->assertOk()->assertJsonPath('data.status.value', 'open');
    $this->getJson("/api/work-orders/{$closed->id}")->assertOk()->assertJsonPath('data.status.value', 'closed');

    expect(Schema::hasColumn('work_orders', 'status'))->toBeFalse();
});

it('create/update: a submitted status is not persisted, not client-writable (AC-033)', function () {
    $actor = workOrderUserWith(['create', 'update', 'view']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Status ignored', 'type' => 'processing', 'status' => 'closed',
    ])->assertCreated();

    expect($response->json('data.status.value'))->toBe('open');
    $this->assertDatabaseMissing('work_orders', ['title' => 'Status ignored', 'status' => 'closed']);

    $workOrder = WorkOrder::find($response->json('data.id'));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['status' => 'closed'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'open');
});

// ---------------------------------------------------------------------------
// delete — AC-060
// ---------------------------------------------------------------------------

it('DELETE /api/work-orders/{id}: 204 + removes the row and its pivot rows (AC-060)', function () {
    $actor = workOrderUserWith(['delete']);
    $workOrder = WorkOrder::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach($line->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$workOrder->id}")->assertNoContent();

    $this->assertDatabaseMissing('work_orders', ['id' => $workOrder->id]);
    $this->assertDatabaseMissing('quote_line_work_order', ['work_order_id' => $workOrder->id]);
});
