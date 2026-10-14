<?php

declare(strict_types=1);

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\BusinessFunction;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestPriority;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0256, D-3/D-4: the RDA grid edits its header cells through the form's
// own PurchaseRequestWriteRequest + PurchaseRequestService (PurchaseRequestCellWriter).

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function patchPurchaseRequestCell(PurchaseRequest $request, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/purchase-requests/rows/{$request->id}", ['column' => $column, 'value' => $value]);
}

it('AC-004: declares the header columns the form edits as single fields', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));

    $editable = collect($this->getJson('/api/tables/purchase-requests/columns')->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();

    expect($editable)->toBe([
        'business_function', 'company_site', 'customer', 'function_manager', 'operational_site', 'priority',
        'requested_at', 'requester', 'subject', 'work_order',
    ]);
});

it('AC-004: declares nothing editable without the update permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));

    $editable = collect($this->getJson('/api/tables/purchase-requests/columns')->assertOk()->json('data.columns'))
        ->where('editable', true)->all();

    expect($editable)->toBe([]);
});

it('AC-004: saves scalar and relation cells through the form path', function () {
    $actor = purchaseRequestUserWith(['view', 'viewAll', 'update']);
    // The commessa picker offers only the work orders the actor can see.
    $actor->givePermissionTo(Permission::findOrCreate('work-orders.viewAll', 'web'));
    Sanctum::actingAs($actor);
    $request = purchaseRequestWithLines();
    $priority = PurchaseRequestPriority::factory()->create(['is_active' => true]);
    $customer = Registry::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $site = CompanySite::factory()->create(['company_id' => $request->company_id]);
    $operationalSite = OperationalSite::factory()->create();
    // Without superiors the requester falls back on the function's manager (spec 0245, D-2).
    $function = BusinessFunction::factory()->create(['manager_id' => $request->function_manager_id]);

    patchPurchaseRequestCell($request, 'subject', 'Printers')->assertOk()->assertJsonPath('data.subject', 'Printers');
    patchPurchaseRequestCell($request, 'requested_at', '2026-11-02')->assertOk()->assertJsonPath('data.requested_at', '2026-11-02');
    patchPurchaseRequestCell($request, 'priority', $priority->id)->assertOk()->assertJsonPath('data.priority.id', $priority->id);
    patchPurchaseRequestCell($request, 'customer', $customer->id)->assertOk()->assertJsonPath('data.customer.id', $customer->id);
    patchPurchaseRequestCell($request, 'customer', null)->assertOk()->assertJsonPath('data.customer', null);
    patchPurchaseRequestCell($request, 'work_order', $workOrder->id)->assertOk()->assertJsonPath('data.work_order.id', $workOrder->id);
    patchPurchaseRequestCell($request, 'company_site', $site->id)->assertOk()->assertJsonPath('data.company_site.id', $site->id);
    patchPurchaseRequestCell($request, 'operational_site', $operationalSite->id)->assertOk()->assertJsonPath('data.operational_site.id', $operationalSite->id);
    patchPurchaseRequestCell($request, 'business_function', $function->id)->assertOk()->assertJsonPath('data.business_function.id', $function->id);

    expect($request->fresh('lines')->lines)->toHaveCount(1);
});

it('AC-004: changes requester and approver within the approver rule', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $approver = User::factory()->create();
    $requester = User::factory()->reportsTo($approver)->create();
    $request = purchaseRequestWithLines(attributes: ['requester_id' => $requester->id]);

    patchPurchaseRequestCell($request, 'function_manager', $approver->id)
        ->assertOk()->assertJsonPath('data.function_manager.id', $approver->id);

    patchPurchaseRequestCell($request, 'requester', User::factory()->create()->id)
        ->assertUnprocessable()->assertJsonValidationErrors('function_manager_id');
});

it('AC-005: the form rules hold in the cell', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines();

    patchPurchaseRequestCell($request, 'subject', '')->assertUnprocessable();
    patchPurchaseRequestCell($request, 'company_site', CompanySite::factory()->create()->id)->assertUnprocessable();
    patchPurchaseRequestCell($request, 'priority', null)->assertUnprocessable();
});

it('AC-004: the company is not editable in the cell', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines();

    patchPurchaseRequestCell($request, 'company', $request->company_id)->assertUnprocessable()->assertJsonValidationErrors('column');
});

it('AC-004: forbids the cell edit without update and marks the row read-only', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $request = purchaseRequestWithLines();

    patchPurchaseRequestCell($request, 'subject', 'Nope')->assertForbidden();
});

it('AC-004: a closed request is a read-only row', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines([LineStatus::Received], ['status' => 'closed', 'closed_at' => now()]);
    $open = purchaseRequestWithLines();

    $rows = collect($this->postJson('/api/tables/purchase-requests/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))
        ->keyBy('id');

    expect($rows[$request->id]['editable'])->toBeFalse()
        ->and($rows[$open->id]['editable'])->toBeTrue();

    patchPurchaseRequestCell($request, 'subject', 'Late')->assertForbidden();
});

it('AC-004: a request outside the visibility scope is not found', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'update']));
    $request = purchaseRequestWithLines();

    patchPurchaseRequestCell($request, 'subject', 'Hidden')->assertNotFound();
});
