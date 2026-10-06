<?php

use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\ProformaRequest;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-002: the draft offers only the lines of the request kind and supplier, with the matching defaults', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $supplierA = Registry::factory()->create();
    $supplierB = Registry::factory()->create();
    $workOrder = proformaWorkOrder([['institution', $supplierA], ['institution', $supplierB], ['consultancy']]);
    $company = Company::factory()->create();
    $workOrder->quote->update(['company_id' => $company->id]);
    $institution = invoiceRequest('institution', $supplierA, $workOrder);
    $consultancy = invoiceRequest('consultancy', null, $workOrder);

    $draft = $this->getJson("/api/proforma-requests/{$institution->id}/invoice-draft")->assertOk()->json('data');
    expect($draft['available_lines'])->toHaveCount(1)
        ->and($draft['defaults']['customer']['id'])->toBe($supplierA->id)
        ->and($draft['defaults']['company']['id'])->toBe($company->id)
        ->and($draft['bank_accounts'])->toBe([]);

    $draft = $this->getJson("/api/proforma-requests/{$consultancy->id}/invoice-draft")->assertOk()->json('data');
    expect($draft['available_lines'])->toHaveCount(1)
        ->and($draft['defaults']['customer']['id'])->toBe($workOrder->quote->opportunity->registry_id);
});

it('AC-003: POST issues the proforma with server-side amounts, schedule and issued request; guards refuse the rest', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $request = invoiceRequest();
    $url = "/api/proforma-requests/{$request->id}/invoice";

    $data = $this->postJson($url, invoicePayload($request))->assertCreated()->assertJsonPath('message', 'Created')->json('data');

    expect($data['type'])->toBe('proforma')->and($data['number_label'])->toBe('1/2026')
        ->and($data['net_amount'])->toBe('30.30')->and($data['vat_amount'])->toBe('6.67')->and($data['total_amount'])->toBe('36.97')
        ->and($data['lines'][0]['net_amount'])->toBe('30.30')
        ->and(array_column($data['installments'], 'amount'))->toBe(['18.48', '18.49'])
        ->and(array_column($data['installments'], 'due_date'))->each->toBeString()
        ->and($data['payment_status'])->toBe('seriously_overdue')->and($data['has_collections'])->toBeFalse();
    $request->refresh();
    expect($request->status->value)->toBe('issued')->and($request->issued_at)->not->toBeNull();

    $this->postJson($url, invoicePayload($request))->assertStatus(409)
        ->assertJsonPath('message', 'This proforma request has already been invoiced.');
    $this->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertStatus(409);
    expect(Invoice::query()->count())->toBe(1);

    $other = invoiceRequest();
    $otherUrl = "/api/proforma-requests/{$other->id}/invoice";
    $this->postJson($otherUrl, invoicePayload($other, ['lines' => [[...invoicePayload($other)['lines'][0], 'unit_price' => '0']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
    $foreignLine = invoiceRequest()->workOrder->quoteLines()->value('quote_lines.id');
    $this->postJson($otherUrl, invoicePayload($other, ['lines' => [[...invoicePayload($other)['lines'][0], 'quote_line_id' => $foreignLine]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.quote_line_id');
    $foreignBank = FinancialAccount::factory()->create(['company_id' => Company::factory()->create()->id]);
    $this->postJson($otherUrl, invoicePayload($other, ['financial_account_id' => $foreignBank->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('financial_account_id');
    expect(Invoice::query()->count())->toBe(1)->and($other->refresh()->status->value)->toBe('pending');
});

it('AC-004: numbers are progressive per company and year and never reused after a delete', function () {
    Sanctum::actingAs(invoiceUserWith(['create', 'delete']));
    $first = invoiceRequest();
    $workOrder = $first->workOrder;
    $issue = fn (ProformaRequest $request, array $overrides = []): array => $this
        ->postJson("/api/proforma-requests/{$request->id}/invoice", invoicePayload($request, $overrides))
        ->assertCreated()->json('data');

    $one = $issue($first);
    $second = invoiceRequest('consultancy', null, $workOrder);
    $two = $issue($second);
    $otherCompany = $issue(invoiceRequest('consultancy', null, $workOrder), ['company_id' => Company::factory()->create()->id]);
    $nextYear = $issue(invoiceRequest('consultancy', null, $workOrder), ['document_date' => '2027-01-10']);

    expect([$one['number'], $two['number'], $otherCompany['number'], $nextYear['number']])->toBe([1, 2, 1, 1])
        ->and($nextYear['number_label'])->toBe('1/2027');

    $this->deleteJson("/api/invoices/{$two['id']}")->assertNoContent();
    expect($issue(invoiceRequest('consultancy', null, $workOrder))['number'])->toBe(3);
});
