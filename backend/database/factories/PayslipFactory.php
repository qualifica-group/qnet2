<?php

namespace Database\Factories;

use App\Enums\PurchaseInvoiceType;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Synthetic payslips for tests only: never real payroll data.
 *
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_invoice_id' => PurchaseInvoice::factory()->state(['document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null]),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'period' => '2026-10',
            'payslip_type' => 'MENSILITA_NORMALE',
            'employee_code' => (string) fake()->numberBetween(1, 200),
        ];
    }
}
