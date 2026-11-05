<?php

use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use App\Services\Commissions\Accruals\CommissionAccrualSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0361 — a write-off is not money: AC-007 (statement, balances, "to verify",
// Management control > Accounts, exposure, commissions).

function writeOffTreasuryActor(): User
{
    Permission::findOrCreate('treasury-movements.view');
    Permission::findOrCreate('management-control.view');
    $user = User::factory()->create();
    $user->givePermissionTo(['treasury-movements.view', 'management-control.view']);
    Sanctum::actingAs($user);

    return $user;
}

/** A collection of `$collected` on the account (null = never collected) closed with a write-off of the rest. */
function writeOffCollection(FinancialAccount $account, string $amount, ?string $collected, string $writeOff, string $date): InvoiceInstallment
{
    $invoice = Invoice::factory()->create(['financial_account_id' => $account->id]);

    return InvoiceInstallment::factory()->writtenOff($writeOff, 'bad_debt')->create([
        'invoice_id' => $invoice->id, 'amount' => $amount,
        'collected_amount' => $collected, 'collected_at' => $date,
    ]);
}

function writeOffPayment(FinancialAccount $account, string $amount, ?string $paid, string $writeOff, string $date): PurchaseInvoiceInstallment
{
    $document = PurchaseInvoice::factory()->create(['financial_account_id' => $account->id]);

    return PurchaseInvoiceInstallment::factory()->writtenOff($writeOff, 'bad_debt')->create([
        'purchase_invoice_id' => $document->id, 'amount' => $amount,
        'paid_amount' => $paid, 'paid_at' => $date,
    ]);
}

it('AC-007: the statement and the balances count only the money collected or paid', function () {
    writeOffTreasuryActor();
    $account = FinancialAccount::factory()->create(['opening_balance' => '0.00']);
    writeOffCollection($account, '1000.00', '999.80', '0.20', '2026-02-10');
    writeOffPayment($account, '500.00', '499.50', '0.50', '2026-02-12');

    $data = $this->getJson("/api/financial-accounts/{$account->id}/statement?date_from=2026-02-01&date_to=2026-02-28")->assertOk()->json('data');

    expect(array_column($data['rows'], 'inflow'))->toBe(['999.80', null])
        ->and(array_column($data['rows'], 'outflow'))->toBe([null, '499.50'])
        ->and($data['closing_balance'])->toBe('500.30')
        ->and($this->getJson('/api/account-statements/balances?date=2026-12-31')->json('data.total'))->toBe('500.30');
});

it('AC-007: a total write-off (collected 0) is neither in the statement nor in "to verify"', function () {
    writeOffTreasuryActor();
    $account = FinancialAccount::factory()->create(['opening_balance' => '10.00']);
    writeOffCollection($account, '300.00', '0.00', '300.00', '2026-02-10');
    writeOffPayment($account, '80.00', null, '80.00', '2026-02-11');
    // same facts with no account at all: they must not show up as unassigned either
    $orphan = Invoice::factory()->create(['financial_account_id' => null]);
    InvoiceInstallment::factory()->writtenOff('50.00', 'bad_debt')->create([
        'invoice_id' => $orphan->id, 'amount' => '50.00', 'collected_amount' => '0.00', 'collected_at' => '2026-02-12',
    ]);

    $data = $this->getJson("/api/financial-accounts/{$account->id}/statement?date_from=2026-02-01&date_to=2026-02-28")->assertOk()->json('data');

    expect($data['rows'])->toBe([])
        ->and($data['closing_balance'])->toBe('10.00')
        ->and($this->getJson('/api/account-statements/unassigned')->json('data'))->toBe(['count' => 0, 'rows' => []]);
});

it('AC-007: Management control > Accounts does not count a total write-off but keeps the real collection', function () {
    writeOffTreasuryActor();
    $account = FinancialAccount::factory()->create();
    $day = now()->startOfMonth()->addDay()->toDateString();
    writeOffCollection($account, '1000.00', '999.80', '0.20', $day);
    writeOffCollection($account, '300.00', '0.00', '300.00', $day);
    writeOffPayment($account, '500.00', '499.50', '0.50', $day);
    writeOffPayment($account, '80.00', null, '80.00', $day);

    $data = mcGet('account-flows', ['date_from' => $day, 'date_to' => now()->endOfMonth()->toDateString()])->assertOk()->json('data');

    expect($data['collections']['total'])->toEqual(999.8)
        ->and($data['collections']['installments'])->toBe(1)
        ->and($data['payments']['total'])->toEqual(499.5)
        ->and($data['payments']['installments'])->toBe(1);
});

it('AC-007: exposure leaves a written-off installment out of the open residual', function () {
    writeOffTreasuryActor();
    $document = mcReceivable([['due' => -20, 'amount' => '1000.00'], ['due' => 10, 'amount' => '400.00']]);
    $document->installments()->orderBy('sequence')->first()->update(['collected_amount' => '999.80', 'collected_at' => mcDay(-1), 'write_off_amount' => '0.20', 'write_off_reason' => 'rounding']);

    $side = mcGet('exposure')->assertOk()->json('data.receivable');

    expect($side['open_total'])->toEqual(400)
        ->and($side['overdue_total'])->toEqual(0);
});

it('AC-007: commissions accrue only on the money collected, a zero collection accrues nothing', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00', '400.00']);
    $first = accrualInstallment($invoice, 0);
    $first->update(['collected_amount' => '0.00', 'collected_at' => '2026-04-20', 'write_off_amount' => '600.00', 'write_off_reason' => 'bad_debt']);

    app(CommissionAccrualSynchronizer::class)->syncInvoice($invoice->fresh(), $first->fresh());

    expect(accrualSum($invoice))->toBe('0.00');

    $second = accrualInstallment($invoice, 1);
    $second->update(['collected_amount' => '400.00', 'collected_at' => '2026-05-20']);
    app(CommissionAccrualSynchronizer::class)->syncInvoice($invoice->fresh(), $second->fresh());

    expect(accrualSum($invoice))->toBe('40.00');
});
