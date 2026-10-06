<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Exceptions\Invoices\InvalidInstallmentConfigurationException;
use App\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Adapter over the pure InstallmentScheduleCalculator: an unusable payment
 * method configuration becomes a 422 on `payment_method_id`.
 */
final class InvoiceInstallmentPlanner
{
    public function __construct(private readonly InstallmentScheduleCalculator $calculator) {}

    /**
     * @return array<int, array{sequence: int, due_date: CarbonImmutable, amount: string, payment_method_code: string|null}>
     */
    public function plan(CarbonImmutable $documentDate, PaymentMethod $method, string $net, string $vat, string $total): array
    {
        try {
            return $this->calculator->calculate($documentDate, $method, $net, $vat, $total);
        } catch (InvalidInstallmentConfigurationException $exception) {
            throw ValidationException::withMessages(['payment_method_id' => [$exception->getMessage()]]);
        }
    }
}
