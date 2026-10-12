<?php

namespace Database\Factories;

use App\Enums\PurchaseInvoiceType;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $net = fake()->randomFloat(2, 100, 5000);
        $vat = round($net * 0.22, 2);

        return [
            'document_type' => PurchaseInvoiceType::Invoice,
            'company_id' => Company::factory(),
            'supplier_id' => Registry::factory()->supplier(),
            'document_number' => (string) fake()->unique()->numberBetween(1, 999999),
            'document_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'payment_method_id' => PaymentMethod::factory(),
            'net_amount' => $net,
            'vat_amount' => $vat,
            'total_amount' => round($net + $vat, 2),
            'created_by' => User::factory(),
        ];
    }

    public function proforma(): static
    {
        return $this->state(fn (): array => ['document_type' => PurchaseInvoiceType::Proforma]);
    }
}
