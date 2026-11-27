<?php

declare(strict_types=1);

use App\Enums\PurchaseInvoiceType;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\PayrollCost;
use App\Models\Payslip;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use Carbon\Carbon;

// Synthetic data only (spec 0434): invented people and amounts.

if (! function_exists('cfpToday')) {
    /** Freezes the day of the snapshot (the cash flow reads "today" from the clock). */
    function cfpToday(string $day): void
    {
        Carbon::setTestNow($day.' 09:00:00');
    }
}

if (! function_exists('cfpCost')) {
    /** The monthly cost of a person in a company. */
    function cfpCost(Company $company, User $user, string $period, string $total, array $attributes = []): PayrollCost
    {
        return PayrollCost::factory()->create(['company_id' => $company->id, 'user_id' => $user->id, 'period' => $period, 'total_cost' => $total, ...$attributes]);
    }
}

if (! function_exists('cfpPayslip')) {
    /** A payslip document: one installment, the net, due on `$due` ("Y-m-d"). */
    function cfpPayslip(Company $company, User $user, string $period, string $net, string $due, string $type = 'MENSILITA_NORMALE'): Payslip
    {
        $invoice = PurchaseInvoice::factory()->create([
            'document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null, 'company_id' => $company->id,
            'net_amount' => $net, 'vat_amount' => '0.00', 'total_amount' => $net,
        ]);
        PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $invoice->id, 'sequence' => 1, 'due_date' => $due, 'amount' => $net]);

        return Payslip::factory()->create(['purchase_invoice_id' => $invoice->id, 'company_id' => $company->id, 'user_id' => $user->id, 'period' => $period, 'payslip_type' => $type]);
    }
}

if (! function_exists('cfpPerson')) {
    /** A person with the October 2026 cost and regular payslip (due 15 November) that make the base period. */
    function cfpPerson(Company $company, string $cost = '2400.00', string $net = '1800.00', array $costAttributes = []): User
    {
        $user = User::factory()->create();
        cfpCost($company, $user, '2026-10', $cost, $costAttributes);
        cfpPayslip($company, $user, '2026-10', $net, '2026-11-15');

        return $user;
    }
}

if (! function_exists('cfpTerminatedProfile')) {
    function cfpTerminatedProfile(User $user, string $day): void
    {
        EmploymentProfile::factory()->create(['user_id' => $user->id, 'terminated_at' => $day]);
    }
}

if (! function_exists('cfpByMonth')) {
    /**
     * The `salaries` and `f24` of the months of a response, by "YYYY-MM".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{salaries: float, f24: float}>
     */
    function cfpByMonth(array $data): array
    {
        $rows = [];
        foreach ($data['months'] as $month) {
            $rows[$month['month']] = ['salaries' => $month['salaries'], 'f24' => $month['f24']];
        }

        return $rows;
    }
}
