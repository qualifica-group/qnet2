<?php

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0271, AC-006 / AC-007 — the proforma cycle: issued, converted, open, and the average times.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

/** The request a proforma comes from, of the given kind. */
function billingRequest(string $kind): int
{
    return ProformaRequest::factory()->create(['kind' => $kind])->id;
}

it('AC-006: issued, converted and open count by creation, first conversion and today, by kind and age', function () {
    $period = ['date_from' => mcDay(-60), 'date_to' => mcDay(0)];
    $first = mcConvert(mcProforma('100.00', 50, ['proforma_request_id' => billingRequest('consultancy')]), 10);
    mcConvert(mcProforma('200.00', 30, ['proforma_request_id' => billingRequest('institution')]), 30);
    mcConvert(mcProforma('400.00', 90), 20);
    mcConvert(mcProforma('800.00', 100), 45);
    mcProforma('1.00', 10, ['proforma_request_id' => billingRequest('consultancy')]);
    mcProforma('2.00', 45, ['proforma_request_id' => billingRequest('institution')]);
    mcProforma('4.00', 80);
    mcProforma('8.00', 120);

    $data = mcGet('billing', $period)->assertOk()->json('data.proforma');

    expect($data['issued'])->toEqual(['documents' => 4, 'amount' => 303])
        ->and($data['converted'])->toEqual(['documents' => 3, 'amount' => 1100, 'average_days_to_invoice' => 28])
        ->and($data['open'])->toEqual(['documents' => 4, 'amount' => 15])
        ->and($data['open_by_age'])->toEqual([
            ['bucket' => '0_30', 'documents' => 1, 'amount' => 1],
            ['bucket' => '31_60', 'documents' => 1, 'amount' => 2],
            ['bucket' => '61_90', 'documents' => 1, 'amount' => 4],
            ['bucket' => 'over_90', 'documents' => 1, 'amount' => 8],
        ])
        ->and($data['by_kind'])->toEqual([
            ['kind' => 'consultancy', 'issued' => 2, 'converted' => 1, 'open' => 1, 'average_days_to_invoice' => 10],
            ['kind' => 'institution', 'issued' => 2, 'converted' => 1, 'open' => 1, 'average_days_to_invoice' => 30],
            ['kind' => null, 'issued' => 0, 'converted' => 1, 'open' => 2, 'average_days_to_invoice' => 45],
        ]);

    // A later downgrade and new conversion does not move the date of the first one
    $first->update(['type' => InvoiceType::Proforma]);
    $first->update(['type' => InvoiceType::Invoice]);
    $window = mcGet('billing', ['date_from' => mcDay(-45), 'date_to' => mcDay(-35)])->assertOk()->json('data.proforma');

    expect($window['converted']['documents'])->toBe(1)->and($window['converted']['amount'])->toEqual(100);
});

it('AC-006: the proforma of another company are left out when the company filter is given', function () {
    $other = Company::factory()->create();
    mcProforma('10.00', 5);
    mcProforma('20.00', 5, ['company_id' => $other->id]);

    $data = mcGet('billing', ['company_id' => [$other->id], 'date_from' => mcDay(-30), 'date_to' => mcDay(0)])->assertOk()->json('data.proforma');

    expect($data['issued'])->toEqual(['documents' => 1, 'amount' => 20])->and($data['open']['documents'])->toBe(1);
});

it('AC-007: the average days to invoice are per document, the collection ones weighted by the collected amount', function () {
    $period = ['date_from' => mcDay(-70), 'date_to' => mcDay(0)];
    $one = mcConvert(mcProforma('100.00', 60, ['proforma_request_id' => billingRequest('consultancy')]), 10);
    $two = mcConvert(mcProforma('300.00', 60, ['proforma_request_id' => billingRequest('institution')]), 30);
    mcCollect($one, '100.00', 45);
    mcCollect($two, '300.00', 15);

    $data = mcGet('billing', $period)->assertOk()->json('data.proforma');

    expect($data['converted']['average_days_to_invoice'])->toBe(20)
        ->and($data['average_days_invoice_to_collection'])->toBe(13)
        ->and(array_column($data['by_kind'], 'average_days_to_invoice'))->toBe([10, 30, null]);

    // A collection outside the period is not part of the average
    $outside = mcGet('billing', ['date_from' => mcDay(-20), 'date_to' => mcDay(0)])->assertOk()->json('data.proforma');
    expect($outside['average_days_invoice_to_collection'])->toBe(15);
});

it('AC-007: without conversions or collections the averages are null', function () {
    mcProforma('50.00', 5);
    mcConvert(mcProforma('70.00', 4), 1);

    $data = mcGet('billing', ['date_from' => mcDay(-30), 'date_to' => mcDay(0)])->assertOk()->json('data.proforma');

    expect($data['converted']['average_days_to_invoice'])->toBe(1)->and($data['average_days_invoice_to_collection'])->toBeNull();

    $empty = mcGet('billing', ['date_from' => mcDay(-300), 'date_to' => mcDay(-290)])->assertOk()->json('data.proforma');
    expect($empty['converted']['average_days_to_invoice'])->toBeNull();
});
