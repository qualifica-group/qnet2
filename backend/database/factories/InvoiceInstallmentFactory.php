<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceInstallment>
 */
class InvoiceInstallmentFactory extends Factory
{
    protected $model = InvoiceInstallment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'sequence' => 1,
            'due_date' => fake()->dateTimeBetween('now', '+60 days')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'payment_method_code' => null,
            'collected_amount' => null,
            'collected_at' => null,
        ];
    }

    public function writtenOff(string $amount = '0.20', string $reason = 'rounding'): static
    {
        return $this->state(fn (array $attributes): array => [
            'write_off_amount' => $amount,
            'write_off_reason' => $reason,
        ]);
    }

    public function collected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'collected_amount' => $attributes['amount'],
            'collected_at' => now()->toDateString(),
        ]);
    }
}
