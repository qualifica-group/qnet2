<?php

use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0360 — unassigned collections/payments: AC-015.

function unassignedActor(bool $view = true): User
{
    Permission::findOrCreate('treasury-movements.view');
    $user = User::factory()->create();

    return $view ? $user->givePermissionTo('treasury-movements.view') : $user;
}

it('AC-015: facts without an account are listed and in no balance; assigning the account moves them', function () {
    Sanctum::actingAs(unassignedActor());
    $account = FinancialAccount::factory()->create();
    $installment = InvoiceInstallment::factory()->create([
        'invoice_id' => Invoice::factory()->create()->id, 'amount' => '300.00', 'collected_amount' => '300.00', 'collected_at' => '2026-02-10',
    ]);
    $payment = PurchaseInvoiceInstallment::factory()->create([
        'purchase_invoice_id' => PurchaseInvoice::factory()->create()->id, 'amount' => '80.00', 'paid_amount' => '80.00', 'paid_at' => '2026-02-11',
    ]);
    InvoiceInstallment::factory()->create([
        'invoice_id' => Invoice::factory()->create()->id, 'amount' => '9.00', 'collected_amount' => '9.00', 'collected_at' => '2025-12-31',
    ]);

    $data = $this->getJson('/api/account-statements/unassigned')->assertOk()->json('data');
    expect($data['count'])->toBe(2)
        ->and(array_column($data['rows'], 'source'))->toBe(['payment', 'collection'])
        ->and($data['rows'][1])->toMatchArray(['installment_id' => $installment->id, 'date' => '2026-02-10', 'amount' => '300.00'])
        ->and($data['rows'][1]['document']['kind'])->toBe('invoice')
        ->and($data['rows'][0]['document']['kind'])->toBe('purchase_invoice')
        ->and($this->getJson('/api/account-statements/balances?date=2026-12-31')->json('data.total'))->toBe('0.00');

    $installment->update(['financial_account_id' => $account->id]);

    expect($this->getJson('/api/account-statements/unassigned')->json('data.count'))->toBe(1)
        ->and($this->getJson('/api/account-statements/balances?date=2026-12-31')->json('data.total'))->toBe('300.00');

    $payment->purchaseInvoice->update(['financial_account_id' => $account->id]);

    expect($this->getJson('/api/account-statements/unassigned')->json('data'))->toBe(['count' => 0, 'rows' => []])
        ->and($this->getJson('/api/account-statements/balances?date=2026-12-31')->json('data.total'))->toBe('220.00');
});

it('AC-015: company filter, 401 and 403', function () {
    $this->getJson('/api/account-statements/unassigned')->assertUnauthorized();

    Sanctum::actingAs(unassignedActor(false));
    $this->getJson('/api/account-statements/unassigned')->assertForbidden();

    Sanctum::actingAs(unassignedActor());
    $invoice = Invoice::factory()->create();
    InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'amount' => '1.00', 'collected_amount' => '1.00', 'collected_at' => '2026-02-10']);

    expect($this->getJson("/api/account-statements/unassigned?company_id={$invoice->company_id}")->json('data.count'))->toBe(1)
        ->and($this->getJson('/api/account-statements/unassigned?company_id='.($invoice->company_id + 1000))->assertStatus(422));
});
