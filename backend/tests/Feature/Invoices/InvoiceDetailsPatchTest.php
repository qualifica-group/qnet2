<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// spec 0224 AC-005 — the in-place rows that do not touch the schedule go through PATCH details.

uses(RefreshDatabase::class);

it('saves the printed notes and the bank through PATCH details without touching the schedule', function (): void {
    Sanctum::actingAs(invoiceUserWith(['create', 'update', 'view']));
    [$invoice] = invoiceWithPlan('1200.00', 3);
    $url = "/api/invoices/{$invoice['id']}/details";
    $bank = FinancialAccount::factory()->create(['company_id' => $invoice['company']['id']]);

    $data = $this->patchJson($url, ['notes' => 'Pay by the 10th'])->assertOk()->json('data');
    expect($data['notes'])->toBe('Pay by the 10th')
        ->and($data['installments'])->toBe($invoice['installments']);

    $data = $this->patchJson($url, ['financial_account_id' => $bank->id])->assertOk()->json('data');
    expect($data['financial_account']['id'])->toBe($bank->id)
        ->and($data['notes'])->toBe('Pay by the 10th')
        ->and($data['installments'])->toBe($invoice['installments']);

    $this->patchJson($url, ['notes' => null, 'financial_account_id' => null])->assertOk()
        ->assertJsonPath('data.notes', null)->assertJsonPath('data.financial_account', null);
});

it('refuses a bank of another company and over-long notes', function (): void {
    Sanctum::actingAs(invoiceUserWith(['create', 'update', 'view']));
    [$invoice] = invoiceWithPlan();
    $url = "/api/invoices/{$invoice['id']}/details";
    $foreignBank = FinancialAccount::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->patchJson($url, ['financial_account_id' => $foreignBank->id])
        ->assertUnprocessable()->assertJsonValidationErrors('financial_account_id');
    $this->patchJson($url, ['notes' => str_repeat('a', 5001)])
        ->assertUnprocessable()->assertJsonValidationErrors('notes');

    expect(Invoice::query()->findOrFail($invoice['id'])->financial_account_id)->toBeNull();
});
