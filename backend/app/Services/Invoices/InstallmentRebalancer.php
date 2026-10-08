<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Pure installment rebalancing (spec 0196): equal split of an amount and the
 * rebalanced plan of an invoice that already has collections. No I/O; amounts
 * are computed in integer cents, the remainder goes on the last installment.
 *
 * @phpstan-type CurrentRow array{id: int, sequence: int, due_date: CarbonImmutable, amount: string, payment_method_code: string|null, collected_amount: string|null}
 * @phpstan-type PlanRow array{id: int|null, sequence: int, due_date: CarbonImmutable, amount: string, payment_method_code: string|null, collected_amount: string|null, locked: bool}
 */
final class InstallmentRebalancer
{
    /** Days after the last due date (or after the partially collected one) of a residual installment. */
    public const int RESIDUAL_INSTALLMENT_DAYS = 30;

    /**
     * @return array<int, string> `$parts` decimal strings summing to `$amount`
     */
    public function equalParts(string $amount, int $parts): array
    {
        $cents = $this->toCents($amount);
        $share = intdiv($cents, $parts);
        $shares = array_fill(0, $parts, $share);
        $shares[$parts - 1] = $cents - $share * ($parts - 1);

        return array_map($this->fromCents(...), $shares);
    }

    /**
     * Rebalanced plan for the new total (D-2, D-3, D-4, D-9): rows with a
     * collection are closed at what was collected, the residual is split on
     * the uncollected ones, which keep their due date.
     *
     * @param  array<int, CurrentRow>  $current  ordered by sequence
     * @return array{rows: array<int, PlanRow>, deleted_ids: array<int, int>}
     *
     * @throws ValidationException when the total is lower than the collected amount
     */
    public function rebalance(array $current, string $newTotal, string $errorField = 'lines'): array
    {
        // Step 1: split the rows in closed (collected) and open
        $collected = 0;
        $closed = [];
        $open = [];

        foreach ($current as $row) {
            $cents = $this->toCents($row['collected_amount'] ?? '0');

            if ($cents > 0) {
                $collected += $cents;
                $closed[] = ['amount' => $this->fromCents($cents), 'locked' => true] + $row;
            } else {
                $open[] = $row;
            }
        }

        // Step 2: the residual can not be negative
        $residual = $this->toCents($newTotal) - $collected;

        if ($residual < 0) {
            throw ValidationException::withMessages([$errorField => ['The document total cannot be lower than the amount already collected.']]);
        }

        // Step 3: spread, drop or append the open installments
        [$opened, $deleted] = $this->openRows($current, $open, $residual);

        $rows = [...array_map($this->planRow(...), $closed), ...$opened];
        usort($rows, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        return ['rows' => $rows, 'deleted_ids' => $deleted];
    }

    /**
     * @param  array<int, CurrentRow>  $current
     * @param  array<int, CurrentRow>  $open
     * @return array{0: array<int, PlanRow>, 1: array<int, int>}
     */
    private function openRows(array $current, array $open, int $residual): array
    {
        if ($residual === 0) {
            return [[], array_column($open, 'id')];
        }

        if ($open === []) {
            $last = $current[array_key_last($current)];

            return [[[
                'id' => null,
                'sequence' => max(array_column($current, 'sequence')) + 1,
                'due_date' => $last['due_date']->addDays(self::RESIDUAL_INSTALLMENT_DAYS),
                'amount' => $this->fromCents($residual),
                'payment_method_code' => $last['payment_method_code'],
                'collected_amount' => null,
                'locked' => false,
            ]], []];
        }

        $amounts = $this->equalParts($this->fromCents($residual), count($open));

        return [array_map(
            fn (array $row, string $amount): array => $this->planRow(['amount' => $amount] + $row),
            $open,
            $amounts,
        ), []];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return PlanRow
     */
    private function planRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'sequence' => $row['sequence'],
            'due_date' => $row['due_date'],
            'amount' => $row['amount'],
            'payment_method_code' => $row['payment_method_code'],
            'collected_amount' => $row['collected_amount'] ?? null,
            'locked' => $row['locked'] ?? false,
        ];
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
