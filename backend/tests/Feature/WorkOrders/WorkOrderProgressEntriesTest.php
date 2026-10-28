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
 * Manual progress entries of a commessa (spec 0321, AC-006..AC-012; revision 2026-10-28: entries are
 * INCREMENTS like the legacy, the POST body carries the new total).
 */

function progressUrl(WorkOrder $workOrder, ?int $entryId = null): string
{
    return "/api/work-orders/{$workOrder->id}/progress-entries".($entryId === null ? '' : "/{$entryId}");
}

/** A commessa with increments 30 (2026-10-01) and 30 (2026-10-10), total 60. */
function workOrderWithProgress(array $attributes = []): WorkOrder
{
    $workOrder = WorkOrder::factory()->create($attributes);
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment(30, '2026-10-01')->create();
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment(30, '2026-10-10')->create();

    return $workOrder;
}

it('lists the entries in insertion order with the Entry shape (AC-006)', function () {
    $actor = workOrderUserWith(['view']);
    Sanctum::actingAs($actor);
    $workOrder = workOrderWithProgress();
    $workOrder->progressEntries()->where('recorded_on', '2026-10-10')->update(['created_by' => $actor->id]);

    $this->getJson(progressUrl($workOrder))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.percentage', 30)
        ->assertJsonPath('data.0.recorded_on', '2026-10-01')
        ->assertJsonPath('data.1.percentage', 30)
        ->assertJsonPath('data.1.recorded_on', '2026-10-10')
        ->assertJsonPath('data.1.created_by.id', $actor->id)
        ->assertJsonPath('data.1.created_by.name', $actor->name)
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

it('creates the increment (new total minus the current SUM) stamped with the actor and logs the activity (AC-007)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    Sanctum::actingAs($actor);
    $workOrder = workOrderWithProgress();

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-10-10'])
        ->assertCreated()
        ->assertJsonPath('data.percentage', 10)
        ->assertJsonPath('data.recorded_on', '2026-10-10')
        ->assertJsonPath('data.created_by.id', $actor->id);

    $entry = $workOrder->progressEntries()->reorder()->latest('id')->firstOrFail();
    expect($entry->percentage)->toBe(10)
        ->and($entry->created_by)->toBe($actor->id)
        ->and(Activity::query()->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)->exists())->toBeTrue();
});

it('accepts the first entry on a commessa without any', function () {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();

    $this->postJson(progressUrl($workOrder), ['percentage' => 1, 'recorded_on' => '2026-01-01'])->assertCreated()
        ->assertJsonPath('data.percentage', 1);
});

it('rejects a total equal or lower than the current SUM, with the legacy message (AC-008)', function (int $percentage) {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = workOrderWithProgress();

    $this->postJson(progressUrl($workOrder), ['percentage' => $percentage, 'recorded_on' => '2026-10-20'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['percentage'])
        ->assertJsonPath('errors.percentage.0', __('work_orders.progress.percentage_not_above_current'));

    expect($workOrder->progressEntries()->count())->toBe(2);
})->with([60, 59, 10]);

it('accepts any date, even before the latest entry (R-2)', function () {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = workOrderWithProgress();

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-09-01'])->assertCreated();
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

it('writes on a force-closed commessa too, no 409 (R-2)', function () {
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $workOrder = workOrderWithProgress(['is_force_closed' => true, 'force_close_reason' => 'Annullata']);
    $entry = $workOrder->progressEntries()->firstOrFail();

    $this->postJson(progressUrl($workOrder), ['percentage' => 70, 'recorded_on' => '2026-10-20'])->assertCreated();
    $this->deleteJson(progressUrl($workOrder, $entry->id))->assertOk();

    expect($workOrder->progressEntries()->count())->toBe(2);
});

it('deletes any entry and the total drops by its increment (R-3)', function () {
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $workOrder = workOrderWithProgress();
    $first = $workOrder->progressEntries()->firstOrFail();
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment(10, '2026-10-12')->create();

    $this->deleteJson(progressUrl($workOrder, $first->id))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);

    expect((int) $workOrder->progressEntries()->sum('percentage'))->toBe(40);
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
