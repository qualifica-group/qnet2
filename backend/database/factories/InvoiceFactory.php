<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /** Incrementing counter backing the (company, year, number) unique key. */
    private static int $nextNumber = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-6 months', 'now');
        $net = fake()->randomFloat(2, 100, 5000);
        $vat = round($net * 0.22, 2);

        return [
            'type' => InvoiceType::Proforma,
            'company_id' => Company::factory(),
            'number' => self::$nextNumber++,
            'year' => (int) $date->format('Y'),
            'document_date' => $date->format('Y-m-d'),
            'customer_registry_id' => Registry::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'net_amount' => $net,
            'vat_amount' => $vat,
            'total_amount' => round($net + $vat, 2),
            'created_by' => User::factory(),
        ];
    }

    public function asInvoice(): static
    {
        return $this->state(fn (): array => [
            'type' => InvoiceType::Invoice,
            'external_number' => (string) fake()->unique()->numberBetween(1, 99999),
            'external_date' => now()->toDateString(),
        ]);
    }
}
