<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

if (! function_exists('reconActor')) {
    /**
     * A user holding exactly the given permissions (full names, e.g. `treasury-movements.reconcile`).
     *
     * @param  array<int, string>  $permissions
     */
    function reconActor(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}

if (! function_exists('reconAccount')) {
    function reconAccount(string $opening = '1000.00'): FinancialAccount
    {
        return FinancialAccount::factory()->create(['opening_balance' => $opening]);
    }
}

if (! function_exists('reconCollection')) {
    /** A collected installment; `$own` = the account is on the installment, else on the document. */
    function reconCollection(FinancialAccount $account, string $amount, string $date, bool $own = true): InvoiceInstallment
    {
        $invoice = Invoice::factory()->create(['financial_account_id' => $own ? null : $account->id]);

        return InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id, 'amount' => $amount, 'collected_amount' => $amount, 'collected_at' => $date,
            'financial_account_id' => $own ? $account->id : null,
        ]);
    }
}

if (! function_exists('reconPayment')) {
    function reconPayment(FinancialAccount $account, string $amount, string $date): PurchaseInvoiceInstallment
    {
        $invoice = PurchaseInvoice::factory()->create(['financial_account_id' => $account->id]);

        return PurchaseInvoiceInstallment::factory()->create([
            'purchase_invoice_id' => $invoice->id, 'amount' => $amount, 'paid_amount' => $amount, 'paid_at' => $date,
        ]);
    }
}

if (! function_exists('reconMovement')) {
    function reconMovement(FinancialAccount $account, string $amount, string $date, bool $income = true): TreasuryMovement
    {
        $factory = $income ? TreasuryMovement::factory()->income() : TreasuryMovement::factory()->expense();

        return $factory->create(['financial_account_id' => $account->id, 'amount' => $amount, 'movement_date' => $date]);
    }
}

if (! function_exists('reconMark')) {
    /**
     * @param  array<int, string>  $keys
     */
    function reconMark(FinancialAccount $account, array $keys, bool $reconciled = true): TestResponse
    {
        return test()->postJson("/api/financial-accounts/{$account->id}/reconciliation-marks", ['keys' => $keys, 'reconciled' => $reconciled]);
    }
}
