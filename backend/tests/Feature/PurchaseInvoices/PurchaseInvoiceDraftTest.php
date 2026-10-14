<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

if (! function_exists('draftActor')) {
    /**
     * A user holding exactly the given purchase-invoices permissions.
     *
     * @param  array<int, string>  $abilities
     */
    function draftActor(array $abilities): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("purchase-invoices.{$ability}", 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}

if (! function_exists('draftOrder')) {
    /**
     * An order with `$lineCount` lines of 100.00 (22% VAT = 22.00) for the given supplier and company.
     *
     * @param  array<string, mixed>  $attributes
     */
    function draftOrder(Registry $supplier, Company $company, PurchaseOrderStatus $status = PurchaseOrderStatus::Approved, int $lineCount = 1, array $attributes = []): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->withStatus($status)->create($attributes + ['supplier_id' => $supplier->id, 'company_id' => $company->id]);
        $vat = VatRate::factory()->create(['rate' => 22]);

        for ($position = 1; $position <= $lineCount; $position++) {
            PurchaseOrderLine::factory()->create([
                'purchase_order_id' => $order->id,
                'position' => $position,
                'vat_rate_id' => $vat->id,
                'vat_amount' => '22.00',
                'total_amount' => '122.00',
            ]);
        }

        return $order->fresh('lines');
    }
}

function draftUrl(array $orders): string
{
    return '/api/purchase-invoices/draft?'.http_build_query(['purchase_order_ids' => collect($orders)->pluck('id')->all()]);
}

it('AC-003: builds the draft from two orders of the same supplier and company with pre-filled lines', function () {
    Sanctum::actingAs(draftActor(['create']));
    $supplier = Registry::factory()->supplier()->create();
    $company = Company::factory()->create();
    $method = PaymentMethod::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $costType = CostType::factory()->create();
    $first = draftOrder($supplier, $company, attributes: ['payment_method_id' => $method->id]);
    $second = draftOrder($supplier, $company, PurchaseOrderStatus::Fulfilled, 2);
    PurchaseOrderLine::query()->whereIn('purchase_order_id', [$first->id, $second->id])->update(['cost_type_id' => $costType->id, 'work_order_id' => $workOrder->id]);

    $data = $this->getJson(draftUrl([$second, $first]))->assertOk()->assertJsonPath('success', true)->json('data');

    expect($data['purchase_order_ids'])->toBe([$first->id, $second->id])
        ->and($data['defaults']['company'])->toBe(['id' => $company->id, 'name' => $company->denomination])
        ->and($data['defaults']['supplier'])->toBe(['id' => $supplier->id, 'name' => $supplier->name])
        ->and($data['defaults']['payment_method'])->toBe(['id' => $method->id, 'name' => $method->name])
        ->and($data['defaults']['cost_type']['id'])->toBe($costType->id)
        ->and($data['defaults']['work_order'])->toBe(['id' => $workOrder->id, 'code' => $workOrder->code, 'title' => $workOrder->title])
        ->and($data['lines'])->toHaveCount(3)
        ->and(array_column($data['lines'], 'sort_order'))->toBe([0, 1, 2])
        ->and(array_column($data['lines'], 'purchase_order_line_id'))->toBe([
            $first->lines[0]->id, $second->lines[0]->id, $second->lines[1]->id,
        ])
        ->and($data['lines'][0])->toMatchArray([
            'id' => null,
            'quantity' => '1.000',
            'unit_price' => '100.00',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'total_amount' => '122.00',
            'purchase_order' => ['id' => $first->id, 'code' => $first->code()],
            'cost_type' => ['id' => $costType->id, 'name' => $costType->name, 'color' => $costType->color],
        ])
        ->and($data['lines'][0]['vat_rate']['rate'])->toEqual(22);
});

it('AC-003: proposes no cost type or work order default when the lines disagree', function () {
    Sanctum::actingAs(draftActor(['create']));
    $supplier = Registry::factory()->supplier()->create();
    $company = Company::factory()->create();
    $order = draftOrder($supplier, $company, lineCount: 2);
    $order->lines[1]->update(['work_order_id' => WorkOrder::factory()->create()->id]);

    $defaults = $this->getJson(draftUrl([$order]))->assertOk()->json('data.defaults');

    expect($defaults['cost_type'])->toBeNull()
        ->and($defaults['work_order'])->toBeNull();
});

it('AC-003: rejects orders of different suppliers or companies with a 422 on purchase_order_ids', function () {
    Sanctum::actingAs(draftActor(['create']));
    $supplier = Registry::factory()->supplier()->create();
    $company = Company::factory()->create();
    $own = draftOrder($supplier, $company);

    $this->getJson(draftUrl([$own, draftOrder(Registry::factory()->supplier()->create(), $company)]))
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_order_ids');
    $this->getJson(draftUrl([$own, draftOrder($supplier, Company::factory()->create())]))
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_order_ids');
});

it('AC-003: rejects orders that are not invoiceable', function (PurchaseOrderStatus $status) {
    Sanctum::actingAs(draftActor(['create']));
    $order = draftOrder(Registry::factory()->supplier()->create(), Company::factory()->create(), $status);

    $this->getJson(draftUrl([$order]))->assertUnprocessable()->assertJsonValidationErrors('purchase_order_ids');
})->with([PurchaseOrderStatus::Draft, PurchaseOrderStatus::InValidation, PurchaseOrderStatus::Cancelled]);

it('AC-003: validates the order ids and requires purchase-invoices.create', function () {
    Sanctum::actingAs(draftActor(['create']));

    $this->getJson('/api/purchase-invoices/draft')->assertUnprocessable()->assertJsonValidationErrors('purchase_order_ids');
    $this->getJson('/api/purchase-invoices/draft?purchase_order_ids[]=999999')->assertUnprocessable();

    $order = draftOrder(Registry::factory()->supplier()->create(), Company::factory()->create());
    Sanctum::actingAs(draftActor(['view']));

    $this->getJson(draftUrl([$order]))->assertForbidden();
});

it('AC-009: proposes only the quantity left to bill and excludes the order lines billed in full', function () {
    Sanctum::actingAs(draftActor(['create']));
    $order = draftOrder(Registry::factory()->supplier()->create(), Company::factory()->create(), lineCount: 3);
    $order->lines[1]->update(['quantity' => '4.000', 'taxable_amount' => '400.00']);
    purchaseOrderReceive($order, [$order->lines[1]->id => '2']);
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $order->lines[0]->id, 'quantity' => '1.000']);
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $order->lines[1]->id, 'quantity' => '1.000']);

    $lines = $this->getJson(draftUrl([$order]))->assertOk()->json('data.lines');

    expect(array_column($lines, 'purchase_order_line_id'))->toBe([$order->lines[1]->id, $order->lines[2]->id])
        ->and($lines[0])->toMatchArray(['quantity' => '3.000', 'available_quantity' => '3.000', 'net_amount' => '300.00', 'vat_amount' => '66.00', 'total_amount' => '366.00']);
});

it('AC-003: lists the invoiceable orders of a supplier and company', function () {
    Sanctum::actingAs(draftActor(['create']));
    $supplier = Registry::factory()->supplier()->create();
    $company = Company::factory()->create();
    $approved = draftOrder($supplier, $company, PurchaseOrderStatus::Approved, attributes: ['order_date' => '2026-05-01']);
    $sent = draftOrder($supplier, $company, PurchaseOrderStatus::SentToSupplier, attributes: ['order_date' => '2026-06-01']);
    draftOrder($supplier, $company, PurchaseOrderStatus::Draft);
    draftOrder($supplier, $company, PurchaseOrderStatus::Cancelled);
    draftOrder(Registry::factory()->supplier()->create(), $company);
    draftOrder($supplier, Company::factory()->create());
    $invoiced = draftOrder($supplier, $company, PurchaseOrderStatus::Fulfilled);
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $invoiced->lines[0]->id]);
    $partial = draftOrder($supplier, $company, PurchaseOrderStatus::PartiallyFulfilled, 2, ['order_date' => '2026-04-01']);
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $partial->lines[0]->id]);

    $data = $this->getJson("/api/purchase-invoices/invoiceable-orders?supplier_id={$supplier->id}&company_id={$company->id}")
        ->assertOk()->json('data');

    expect(array_column($data, 'id'))->toBe([$sent->id, $approved->id, $partial->id])
        ->and($data[0])->toBe([
            'id' => $sent->id,
            'code' => $sent->code(),
            'order_date' => '2026-06-01',
            'grand_total' => $sent->grand_total,
            'status' => 'sent_to_supplier',
        ]);
});

it('AC-003: invoiceable orders validate the filters and require purchase-invoices.create', function () {
    Sanctum::actingAs(draftActor(['create']));

    $this->getJson('/api/purchase-invoices/invoiceable-orders')->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'company_id']);

    Sanctum::actingAs(draftActor(['view']));
    $supplier = Registry::factory()->supplier()->create();

    $this->getJson("/api/purchase-invoices/invoiceable-orders?supplier_id={$supplier->id}&company_id=".Company::factory()->create()->id)->assertForbidden();
});
