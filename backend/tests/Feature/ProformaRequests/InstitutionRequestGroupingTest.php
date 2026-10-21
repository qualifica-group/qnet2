<?php

use App\Models\Company;
use App\Models\ProformaRequest;
use App\Models\Registry;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0288 D-1/D-2: institution requests group by (supplier, issuing company)
 * across the whole work order; consultancy requests stay one per offer.
 */
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(proformaUserWith(['create'])));

it('AC-001: the same supplier on two offers of the same company raises one request on the lowest-code offer', function () {
    $company = Company::factory()->create();
    $supplier = Registry::factory()->create();
    $workOrder = WorkOrder::factory()->multiQuote()->create();
    $second = institutionOffer($company, 'QUO-10000');
    $first = institutionOffer($company, 'QUO-9999');
    institutionLine($workOrder, $second, $supplier);
    institutionLine($workOrder, $first, $supplier);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();

    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->supplier_id)->toBe($supplier->id)
        ->and($rows->first()->quote_id)->toBe($first->id)
        ->and($rows->first()->payment_method_id)->toBe($first->payment_method_id);
});

it('AC-002: the same supplier on offers of two companies raises one request per company', function () {
    $supplier = Registry::factory()->create();
    $workOrder = WorkOrder::factory()->multiQuote()->create();
    $offerA = institutionOffer(Company::factory()->create(), 'QUO-0001');
    $offerB = institutionOffer(Company::factory()->create(), 'QUO-0002');
    institutionLine($workOrder, $offerA, $supplier);
    institutionLine($workOrder, $offerB, $supplier);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();

    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('supplier_id')->unique()->all())->toBe([$supplier->id])
        ->and($rows->pluck('quote_id')->sort()->values()->all())->toBe([$offerA->id, $offerB->id]);
});

it('AC-002: distinct suppliers of the same company still raise one request each', function () {
    $company = Company::factory()->create();
    $workOrder = WorkOrder::factory()->multiQuote()->create();
    $offer = institutionOffer($company, 'QUO-0001');
    $supplierA = Registry::factory()->create();
    $supplierB = Registry::factory()->create();
    institutionLine($workOrder, $offer, $supplierA);
    institutionLine($workOrder, $offer, $supplierB);
    institutionLine($workOrder, institutionOffer($company, 'QUO-0002'), null);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();

    expect(ProformaRequest::query()->where('work_order_id', $workOrder->id)->pluck('supplier_id')->all())
        ->toEqualCanonicalizing([$supplierA->id, $supplierB->id, null]);
});

it('AC-003: consultancy requests stay one per offer', function () {
    $company = Company::factory()->create();
    $workOrder = WorkOrder::factory()->multiQuote()->create();
    $offerA = institutionOffer($company, 'QUO-0001');
    $offerB = institutionOffer($company, 'QUO-0002');
    institutionLine($workOrder, $offerA, null, 'consultancy');
    institutionLine($workOrder, $offerB, null, 'consultancy');

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();

    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get();
    expect($rows->pluck('kind')->map->value->unique()->all())->toBe(['consultancy'])
        ->and($rows->pluck('quote_id')->sort()->values()->all())->toBe([$offerA->id, $offerB->id]);
});
