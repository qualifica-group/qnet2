<?php

namespace Database\Factories;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoiceInstallment>
 */
class PurchaseInvoiceInstallmentFactory extends Factory
{
    protected $model = PurchaseInvoiceInstallment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_invoice_id' => PurchaseInvoice::factory(),
            'sequence' => 1,
            'due_date' => fake()->dateTimeBetween('now', '+60 days')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'payment_method_code' => null,
            'paid_amount' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paid_amount' => $attributes['amount'],
            'paid_at' => now()->toDateString(),
        ]);
    }
}
