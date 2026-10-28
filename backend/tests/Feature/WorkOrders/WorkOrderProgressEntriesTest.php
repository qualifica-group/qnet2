<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * Manual progress entries of a commessa (spec 0321, AC-006..AC-012).
 */

function progressUrl(WorkOrder $workOrder, ?int $entryId = null): string
{
    return "/api/work-orders/{$workOrder->id}/progress-entries".($entryId === null ? '' : "/{$entryId}");
}

/** A commessa with entries 30 (2026-10-01) and 60 (2026-10-10). */
function workOrderWithProgress(array $attributes = []): WorkOrder
{
    $workOrder = WorkOrder::factory()->create($attributes);
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->reached(30, '2026-10-01')->create();
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->reached(60, '2026-10-10')->create();

    return $workOrder;
}

it('lists the entries ordered by percentage desc with the Entry shape (AC-006)', function () {
    $actor = workOrderUserWith(['view']);
    Sanctum::actingAs($actor);
    $workOrder = workOrderWithProgress();
    $workOrder->progressEntries()->where('percentage', 60)->update(['created_by' => $actor->id]);

    $this->getJson(progressUrl($workOrder))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.percentage', 60)
        ->assertJsonPath('data.0.recorded_on', '2026-10-10')
        ->assertJsonPath('data.0.created_by.id', $actor->id)
        ->assertJsonPath('data.0.created_by.name', $actor->name)
        ->assertJsonPath('data.1.percentage', 30)
        ->assertJsonStructure(['data' => [['id', 'percentage', 'recorded_on', 'created_by', 'created_at']]]);
});

it('forbids the list without view or outside the visibility scope (AC-006)', function () {
    $workOrder = workOrderWithProgress();

    Sanctum::actingAs(workOrderUserWith([]));
    $this->getJson(progressUrl($workOrder))->assertForbidden();

    Permission::findOrCreate('work-orders.view');
    $scoped = User::factory()->create();
    $scoped->givePermissionTo('work-orders.view');
    Sanctum::actingAs($scoped);
    $this->getJson(progressUrl($workOrder))->assertForbidden();
});

it('creates an entry stamped with the actor and logs the activity (AC-007)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    Sanctum::actingAs($actor);
    $workOrder = workOrderWithProgress();

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-10-10'])
        ->assertCreated()
        ->assertJsonPath('data.percentage', 70)
        ->assertJsonPath('data.recorded_on', '2026-10-10')
        ->assertJsonPath('data.created_by.id', $actor->id);

    $entry = WorkOrderProgressEntry::query()->where('percentage', 70)->firstOrFail();
    expect($entry->created_by)->toBe($actor->id)
        ->and(Activity::query()->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)->exists())->toBeTrue();
});

it('accepts the first entry on a commessa without any', function () {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();

    $this->postJson(progressUrl($workOrder), ['percentage' => 1, 'recorded_on' => '2026-01-01'])->assertCreated();
});

it('rejects a percentage equal or lower than the current one (AC-008)', function (int $percentage) {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = workOrderWithProgress();

    $this->postJson(progressUrl($workOrder), ['percentage' => $percentage, 'recorded_on' => '2026-10-20'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['percentage']);

    expect($workOrder->progressEntries()->count())->toBe(2);
})->with([60, 59, 10]);

it('rejects a date before the latest entry and allows the same date (AC-009)', function () {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = workOrderWithProgress();

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-10-09'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recorded_on']);

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-10-10'])->assertCreated();
});

it('validates the base rules (AC-010)', function (array $payload, string $field) {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();

    $this->postJson(progressUrl($workOrder), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'zero' => [['percentage' => 0, 'recorded_on' => '2026-10-10'], 'percentage'],
    'over 100' => [['percentage' => 101, 'recorded_on' => '2026-10-10'], 'percentage'],
    'not integer' => [['percentage' => 12.5, 'recorded_on' => '2026-10-10'], 'percentage'],
    'not numeric' => [['percentage' => 'abc', 'recorded_on' => '2026-10-10'], 'percentage'],
    'missing percentage' => [['recorded_on' => '2026-10-10'], 'percentage'],
    'missing date' => [['percentage' => 10], 'recorded_on'],
    'bad date' => [['percentage' => 10, 'recorded_on' => 'not-a-date'], 'recorded_on'],
]);

it('forbids writes without update or outside the visibility scope (AC-011)', function () {
    $workOrder = workOrderWithProgress();
    $entry = $workOrder->progressEntries()->firstOrFail();
    $payload = ['percentage' => 70, 'recorded_on' => '2026-10-20'];

    Sanctum::actingAs(workOrderUserWith(['view']));
    $this->postJson(progressUrl($workOrder), $payload)->assertForbidden();
    $this->deleteJson(progressUrl($workOrder, $entry->id))->assertForbidden();

    Permission::findOrCreate('work-orders.view');
    Permission::findOrCreate('work-orders.update');
    $scoped = User::factory()->create();
    $scoped->givePermissionTo(['work-orders.view', 'work-orders.update']);
    Sanctum::actingAs($scoped);
    $this->postJson(progressUrl($workOrder), $payload)->assertForbidden();
    $this->deleteJson(progressUrl($workOrder, $entry->id))->assertForbidden();

    expect($workOrder->progressEntries()->count())->toBe(2);
});

it('answers 409 on a force-closed commessa for POST and DELETE (AC-011)', function () {
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $workOrder = workOrderWithProgress(['is_force_closed' => true, 'force_close_reason' => 'Annullata']);
    $entry = $workOrder->progressEntries()->firstOrFail();

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-10-20'])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('work_orders.progress.work_order_closed'));
    $this->deleteJson(progressUrl($workOrder, $entry->id))->assertStatus(409);

    $this->getJson(progressUrl($workOrder))->assertOk()->assertJsonCount(2, 'data');
    expect($workOrder->progressEntries()->count())->toBe(2);
});

it('deletes an entry and the current percentage reverts to the previous one (AC-012)', function () {
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $workOrder = workOrderWithProgress();
    $top = $workOrder->progressEntries()->firstOrFail();

    $this->deleteJson(progressUrl($workOrder, $top->id))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);

    expect($workOrder->progressEntries()->reorder()->max('percentage'))->toBe(30);
});

it('answers 404 when deleting an entry of another commessa (AC-012)', function () {
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $workOrder = workOrderWithProgress();
    $other = workOrderWithProgress();
    $foreign = $other->progressEntries()->firstOrFail();

    $this->deleteJson(progressUrl($workOrder, $foreign->id))->assertNotFound();

    expect(WorkOrderProgressEntry::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('offers the progress row action to whoever may view the work order', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    WorkOrder::factory()->create(['title' => 'Visibile']);

    $items = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    $keys = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.actions'))->pluck('key');

    expect(collect($items)->firstWhere('title', 'Visibile')['actions'])->toContain('progress')
        ->and($keys)->toContain('progress');
});
