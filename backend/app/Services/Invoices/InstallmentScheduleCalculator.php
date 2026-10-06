<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\VatAllocation;
use App\Exceptions\Invoices\InvalidInstallmentConfigurationException;
use App\Models\PaymentMethod;
use Carbon\CarbonImmutable;

/**
 * Pure installment schedule calculator (spec 0194, D-10/D-11): no I/O, the
 * PaymentMethod is read as a configuration holder (it need not be persisted).
 * Amounts are computed in integer cents, the remainder goes on the last
 * installment, so the sum of the installments always equals the total.
 */
final class InstallmentScheduleCalculator
{
    /** Days per "month" when an end-of-month term is expressed in days (legacy "30 gg DF FM"). */
    private const int DAYS_PER_MONTH = 30;

    /**
     * @param  string  $net  decimal string, e.g. "1000.00"
     * @param  string  $vat  decimal string
     * @param  string  $total  decimal string (the sum of the installments)
     * @return array<int, array{sequence: int, due_date: CarbonImmutable, amount: string, payment_method_code: string|null}>
     *
     * @throws InvalidInstallmentConfigurationException
     */
    public function calculate(CarbonImmutable $documentDate, PaymentMethod $method, string $net, string $vat, string $total): array
    {
        // Step 1: read the configuration, rejecting the impossible combination
        $count = max(1, (int) ($method->installments_count ?? 1));
        $allocation = $method->vat_allocation ?? VatAllocation::Split;

        if ($allocation === VatAllocation::VatFirst && $count < 2) {
            throw InvalidInstallmentConfigurationException::vatFirstNeedsTwoInstallments();
        }

        // Step 2: amounts per installment, in cents
        $amounts = $this->amountsInCents($allocation, $count, $this->toCents($net), $this->toCents($vat), $this->toCents($total));

        // Step 3: due dates and result rows
        $schedule = [];

        foreach ($amounts as $index => $cents) {
            $sequence = $index + 1;
            $schedule[] = [
                'sequence' => $sequence,
                'due_date' => $this->dueDate($documentDate, $method, $sequence),
                'amount' => $this->fromCents($cents),
                'payment_method_code' => $method->payment_method_code,
            ];
        }

        return $schedule;
    }

    /**
     * @return array<int, int>
     */
    private function amountsInCents(VatAllocation $allocation, int $count, int $net, int $vat, int $total): array
    {
        $amounts = match ($allocation) {
            VatAllocation::Split => $this->spread($total, $count),
            VatAllocation::First => $this->withVatOn($this->spread($net, $count), 0, $vat),
            VatAllocation::Last => $this->withVatOn($this->spread($net, $count), $count - 1, $vat),
            VatAllocation::VatFirst => [$vat, ...$this->spread($net, $count - 1)],
        };

        // The last installment absorbs any rounding residue so the sum is the total.
        $amounts[$count - 1] += $total - array_sum($amounts);

        return $amounts;
    }

    /**
     * @return array<int, int>
     */
    private function spread(int $cents, int $parts): array
    {
        $share = intdiv($cents, $parts);
        $amounts = array_fill(0, $parts, $share);
        $amounts[$parts - 1] = $cents - $share * ($parts - 1);

        return $amounts;
    }

    /**
     * @param  array<int, int>  $amounts
     * @return array<int, int>
     */
    private function withVatOn(array $amounts, int $index, int $vat): array
    {
        $amounts[$index] += $vat;

        return $amounts;
    }

    private function dueDate(CarbonImmutable $documentDate, PaymentMethod $method, int $sequence): CarbonImmutable
    {
        $firstDays = (int) ($method->payment_days ?? 0);
        $betweenDays = (int) ($method->days_between_installments ?? 0);

        if (! $method->end_of_month) {
            return $documentDate->addDays($firstDays + ($sequence - 1) * $betweenDays);
        }

        $months = $this->ceilMonths($firstDays) + ($sequence - 1) * $this->ceilMonths($betweenDays);

        return $documentDate
            ->addMonthsNoOverflow($months)
            ->endOfMonth()
            ->startOfDay()
            ->addDays((int) ($method->end_of_month_extra_days ?? 0));
    }

    private function ceilMonths(int $days): int
    {
        return intdiv($days + self::DAYS_PER_MONTH - 1, self::DAYS_PER_MONTH);
    }

    private function toCents(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }

    private function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }
}
