<?php

use App\Models\Product;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Notifications\WorkOrderLineDeliverableNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * Bulk payment data of a commessa's lines (spec 0333): PATCH
 * /api/work-orders/{workOrder}/contract-data/lines, AC-001..AC-003.
 */
uses(RefreshDatabase::class);

function bulkLinePaymentUrl(WorkOrder $workOrder): string
{
    return "/api/work-orders/{$workOrder->id}/contract-data/lines";
}

it('AC-001: sets the status on every selected line, only the submitted keys, other lines untouched', function () {
    $workOrder = WorkOrder::factory()->create();
    $first = contractDataLine($workOrder, 'consultancy', 500);
    $second = contractDataLine($workOrder, 'consultancy', 300);
    $untouched = contractDataLine($workOrder, 'consultancy', 100);
    $previous = WorkOrderPaymentStatus::factory()->create();
    $paid = WorkOrderPaymentStatus::factory()->create(['name' => 'Saldato']);
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $second->id,
        'work_order_payment_status_id' => $previous->id, 'payment_agreement' => 'Keep me', 'has_unpaid' => true,
    ]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $response = $this->patchJson(bulkLinePaymentUrl($workOrder), [
        'quote_line_ids' => [$first->id, $second->id],
        'work_order_payment_status_id' => $paid->id,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.lines')
        ->assertJsonStructure(['data' => ['lines', 'totals', 'commissions_visible']]);

    $byLine = collect($response->json('data.lines'))->keyBy('quote_line_id');
    expect($byLine[$first->id]['payment']['status']['id'])->toBe($paid->id)
        ->and($byLine[$second->id]['payment']['status']['id'])->toBe($paid->id)
        ->and($byLine[$second->id]['payment']['payment_agreement'])->toBe('Keep me')
        ->and($byLine[$second->id]['payment']['has_unpaid'])->toBeTrue()
        ->and($byLine[$untouched->id]['payment']['status'])->toBeNull();

    expect(WorkOrderLinePayment::count())->toBe(2);
});

it('AC-001: sets the unpaid flag alone, and null clears the status', function () {
    $workOrder = WorkOrder::factory()->create();
    $first = contractDataLine($workOrder, 'consultancy', 500);
    $second = contractDataLine($workOrder, 'consultancy', 300);
    $status = WorkOrderPaymentStatus::factory()->create();
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $first->id, 'work_order_payment_status_id' => $status->id,
    ]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$first->id, $second->id], 'has_unpaid' => true])
        ->assertOk();

    expect(WorkOrderLinePayment::where('has_unpaid', true)->count())->toBe(2)
        ->and(WorkOrderLinePayment::firstWhere('quote_line_id', $first->id)->work_order_payment_status_id)->toBe($status->id);

    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$first->id], 'work_order_payment_status_id' => null])
        ->assertOk();

    expect(WorkOrderLinePayment::firstWhere('quote_line_id', $first->id)->work_order_payment_status_id)->toBeNull();
});

it('AC-002: 401 anonymous, 403 without managePayments or out of scope', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $payload = ['quote_line_ids' => [$line->id], 'has_unpaid' => true];

    $this->patchJson(bulkLinePaymentUrl($workOrder), $payload)->assertUnauthorized();

    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));
    $this->patchJson(bulkLinePaymentUrl($workOrder), $payload)->assertForbidden();

    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments', 'view'], viewAll: false));
    $this->patchJson(bulkLinePaymentUrl($workOrder), $payload)->assertForbidden();

    expect(WorkOrderLinePayment::count())->toBe(0);
});

it('AC-002: 422 for a foreign line (nothing written), an inactive status, no value to set, or no lines', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $foreign = contractDataLine(WorkOrder::factory()->create(), 'consultancy', 100);
    $inactive = WorkOrderPaymentStatus::factory()->create(['is_active' => false]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$line->id, $foreign->id], 'has_unpaid' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('quote_line_ids.1');
    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$line->id], 'work_order_payment_status_id' => $inactive->id])
        ->assertUnprocessable()->assertJsonValidationErrors('work_order_payment_status_id');
    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$line->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('work_order_payment_status_id');
    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [], 'has_unpaid' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('quote_line_ids');
    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$line->id], 'has_unpaid' => 'maybe'])
        ->assertUnprocessable()->assertJsonValidationErrors('has_unpaid');

    expect(WorkOrderLinePayment::count())->toBe(0);
});

it('AC-003: lines turning deliverable send ONE notification per recipient listing every product, never to the actor', function () {
    Notification::fake();
    $workOrder = WorkOrder::factory()->create();
    $first = contractDataLine($workOrder, 'consultancy', 500);
    $second = contractDataLine($workOrder, 'consultancy', 300);
    $alreadyDeliverable = contractDataLine($workOrder, 'consultancy', 100);
    $deliverable = WorkOrderPaymentStatus::factory()->deliverable()->create(['name' => 'Saldato']);
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $alreadyDeliverable->id,
        'work_order_payment_status_id' => WorkOrderPaymentStatus::factory()->deliverable()->create()->id,
    ]);
    $actor = workOrderPaymentsUserWith(['managePayments']);
    $supervisor = User::factory()->create();
    $workOrder->supervisors()->attach([$actor->id, $supervisor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson(bulkLinePaymentUrl($workOrder), [
        'quote_line_ids' => [$first->id, $second->id, $alreadyDeliverable->id],
        'work_order_payment_status_id' => $deliverable->id,
    ])->assertOk();

    Notification::assertSentToTimes($supervisor, WorkOrderLineDeliverableNotification::class, 1);
    Notification::assertNotSentTo($actor, WorkOrderLineDeliverableNotification::class);
    Notification::assertSentTo($supervisor, WorkOrderLineDeliverableNotification::class, function ($notification) use ($supervisor, $first, $second, $alreadyDeliverable): bool {
        $message = $notification->toArray($supervisor)['message'];
        expect($message)->toContain(Product::find($first->product_id)->name)
            ->and($message)->toContain(Product::find($second->product_id)->name)
            ->and($message)->not->toContain(Product::find($alreadyDeliverable->product_id)->name)
            ->and($message)->toContain('Saldato');

        return true;
    });
});

it('AC-003: no notification when only the unpaid flag changes', function () {
    Notification::fake();
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $supervisor = User::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(bulkLinePaymentUrl($workOrder), ['quote_line_ids' => [$line->id], 'has_unpaid' => true])->assertOk();

    Notification::assertNothingSent();
});
