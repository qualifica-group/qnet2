<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Notifications\AssignmentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

// Task 428419: adding a Responsabile sets the commessa's "Data di assegnazione"
// (`data_inizio_lavorazione`) to today, as the legacy did.

uses(RefreshDatabase::class);

const ASSIGNMENT_DATE_CODE = 'data_inizio_lavorazione';

beforeEach(function () {
    // 23:30 UTC on Nov 13 is already Nov 14 in the office.
    Carbon::setTestNow(Carbon::parse('2026-11-13 23:30:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A commessa whose category carries the assignment-date attribute.
 */
function assignmentDateWorkOrder(array $values = []): WorkOrder
{
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => ASSIGNMENT_DATE_CODE]);
    $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));

    if ($values !== []) {
        $workOrder->forceFill(['attribute_values' => $values])->save();
    }

    return $workOrder;
}

it('sets the assignment date to the office day when a Responsabile is added', function () {
    $workOrder = assignmentDateWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [User::factory()->create()->id]])->assertOk();

    expect($workOrder->fresh()->attribute_values)->toBe([ASSIGNMENT_DATE_CODE => '2026-11-14']);
});

it('moves an older assignment date forward on every new Responsabile, as the legacy', function () {
    $first = User::factory()->create();
    $workOrder = assignmentDateWorkOrder([ASSIGNMENT_DATE_CODE => '2026-10-01']);
    $workOrder->supervisors()->sync([$first->id]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [$first->id, User::factory()->create()->id]])->assertOk();

    expect($workOrder->fresh()->attribute_values[ASSIGNMENT_DATE_CODE])->toBe('2026-11-14');
});

it('leaves the date alone when no Responsabile is added', function () {
    $kept = User::factory()->create();
    $removed = User::factory()->create();
    $workOrder = assignmentDateWorkOrder([ASSIGNMENT_DATE_CODE => '2026-10-01']);
    $workOrder->supervisors()->sync([$kept->id, $removed->id]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [$kept->id]])->assertOk();
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'Rinominata'])->assertOk();

    expect($workOrder->fresh()->attribute_values[ASSIGNMENT_DATE_CODE])->toBe('2026-10-01');
});

it('keeps a date typed by hand in the same save', function () {
    $workOrder = assignmentDateWorkOrder([ASSIGNMENT_DATE_CODE => '2026-10-01']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'supervisor_ids' => [User::factory()->create()->id],
        'attribute_values' => [ASSIGNMENT_DATE_CODE => '2026-11-02'],
    ])->assertOk();

    expect($workOrder->fresh()->attribute_values[ASSIGNMENT_DATE_CODE])->toBe('2026-11-02');
});

it('writes nothing on a commessa whose categories lack the field', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id, 'type' => 'processing']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [User::factory()->create()->id]])->assertOk();

    expect($workOrder->fresh()->attribute_values)->toBeNull();
});

it('sets the date when the commessa is born with a Responsabile', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => ASSIGNMENT_DATE_CODE]);
    Sanctum::actingAs(workOrderUserWith(['create', 'view']));
    // Each line belongs to one commessa only: a fresh offer per creation.
    $payload = function (array $supervisorIds) use ($category): array {
        $quote = categoryTabQuote([$category]);

        return [
            ...workOrderRequiredFields(),
            'quote_id' => $quote->id,
            'title' => 'Commessa',
            'type' => 'processing',
            'quote_line_ids' => $quote->offerLines()->pluck('id')->all(),
            'supervisor_ids' => $supervisorIds,
        ];
    };

    $withSupervisor = $this->postJson('/api/work-orders', $payload([User::factory()->create()->id]))->assertCreated();
    $withoutSupervisor = $this->postJson('/api/work-orders', $payload([]))->assertCreated();

    expect(WorkOrder::query()->find($withSupervisor->json('data.id'))->attribute_values)->toBe([ASSIGNMENT_DATE_CODE => '2026-11-14'])
        ->and(WorkOrder::query()->find($withoutSupervisor->json('data.id'))->attribute_values)->toBeNull();
});

it('leaves a system write (legacy migration) alone', function () {
    $workOrder = assignmentDateWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    AssignmentNotifier::muted(fn () => $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [User::factory()->create()->id]])->assertOk());

    expect($workOrder->fresh()->attribute_values)->toBeNull();
});

it('shows the new date in the grid row returned by the Responsabili cell edit', function () {
    $workOrder = assignmentDateWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $response = $this->patchJson("/api/tables/work-orders/rows/{$workOrder->id}", [
        'column' => 'supervisors',
        'value' => [User::factory()->create()->id],
    ])->assertOk();

    expect($response->json('data')['attr.'.ASSIGNMENT_DATE_CODE])->toBe('2026-11-14');
});
