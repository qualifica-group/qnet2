<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Migrations\Realign\LegacyRealignNotes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Aligns the schedule of an invoice, active or passive (spec 0375 D-14). A payment
 * recorded on qnet-2 is never cleared or rewritten: when it disagrees with the
 * legacy the installment keeps it and the report says so.
 */
final class InvoiceScheduleAligner
{
    private const int MONEY_SCALE = 2;

    /**
     * @param  Collection<int, Model>  $installments  ordered by `sequence`
     * @param  list<array<string, mixed>>  $legacy  in the qnet-2 shape, ordered by `sequence`
     * @param  string  $paidAmount  the payment amount column of this schedule
     * @param  string  $paidOn  the payment date column of this schedule
     * @return bool whether any installment changed
     */
    public function align(Collection $installments, array $legacy, string $paidAmount, string $paidOn, LegacyRealignNotes $notes): bool
    {
        // Step 1: without a one-to-one schedule there is nothing safe to compare.
        if ($legacy === []) {
            return false;
        }

        if (count($legacy) !== $installments->count()) {
            $notes->warn('The legacy has '.count($legacy).' installments and qnet-2 has '.$installments->count().'; the schedule is not updated.');

            return false;
        }

        // Step 2: amounts move only all together, so their sum stays the document total.
        $amountsWritable = $installments->every(fn (Model $row): bool => ! $this->isSettled($row, $paidAmount));
        $changed = false;

        // Step 3: one installment at a time.
        foreach ($installments->values() as $index => $installment) {
            $changed = $this->alignOne($installment, $legacy[$index], $paidAmount, $paidOn, $amountsWritable, $notes) || $changed;
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function alignOne(Model $row, array $legacy, string $paidAmount, string $paidOn, bool $amountsWritable, LegacyRealignNotes $notes): bool
    {
        if ($this->isLocked($row)) {
            $this->warnWhenDifferent($row, $legacy, $paidAmount, $notes);

            return false;
        }

        $this->fillPlan($row, $legacy, $amountsWritable, $notes);
        $this->fillPayment($row, $legacy, $paidAmount, $paidOn, $notes);

        if (! $row->isDirty()) {
            return false;
        }

        $row->saveQuietly();

        return true;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function fillPlan(Model $row, array $legacy, bool $amountsWritable, LegacyRealignNotes $notes): void
    {
        $row->forceFill([
            'due_date' => filled($legacy['due_date'] ?? null) ? $legacy['due_date'] : $row->getAttribute('due_date'),
            'payment_method_code' => $legacy['payment_method_code'] ?? null,
        ]);

        if ($this->sameMoney($row->getAttribute('amount'), $legacy['amount'] ?? null)) {
            return;
        }

        if ($amountsWritable) {
            $row->forceFill(['amount' => $legacy['amount']]);
        } else {
            $notes->warn("Installment {$row->getAttribute('sequence')}: legacy amount differs but the schedule has payments on qnet-2; kept.");
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function fillPayment(Model $row, array $legacy, string $paidAmount, string $paidOn, LegacyRealignNotes $notes): void
    {
        $current = $row->getAttribute($paidAmount);
        $target = $legacy[$paidAmount] ?? null;

        if ($current === null && $target === null) {
            return;
        }

        if ($current === null) {
            $row->forceFill([$paidAmount => $target, $paidOn => $legacy[$paidOn] ?? null]);

            return;
        }

        if (! $this->sameMoney($current, $target) || ! $this->sameDate($row->getAttribute($paidOn), $legacy[$paidOn] ?? null)) {
            $notes->warn("Installment {$row->getAttribute('sequence')}: the payment on qnet-2 differs from the legacy; kept.");
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function warnWhenDifferent(Model $row, array $legacy, string $paidAmount, LegacyRealignNotes $notes): void
    {
        $samePlan = $this->sameMoney($row->getAttribute('amount'), $legacy['amount'] ?? null)
            && $this->sameDate($row->getAttribute('due_date'), $legacy['due_date'] ?? null);

        if (! $samePlan || ! $this->sameMoney($row->getAttribute($paidAmount), $legacy[$paidAmount] ?? null)) {
            $notes->warn("Installment {$row->getAttribute('sequence')}: reconciled, compensated or written off on qnet-2; kept.");
        }
    }

    private function isSettled(Model $row, string $paidAmount): bool
    {
        return $this->isLocked($row) || bccomp((string) ($row->getAttribute($paidAmount) ?? '0'), '0', self::MONEY_SCALE) > 0;
    }

    private function isLocked(Model $row): bool
    {
        return $row->getAttribute('reconciled_at') !== null
            || $row->getAttribute('compensation_id') !== null
            || bccomp((string) ($row->getAttribute('write_off_amount') ?? '0'), '0', self::MONEY_SCALE) > 0;
    }

    private function sameMoney(mixed $first, mixed $second): bool
    {
        if ($first === null || $second === null) {
            return $first === $second;
        }

        return bccomp((string) $first, number_format((float) $second, self::MONEY_SCALE, '.', ''), self::MONEY_SCALE) === 0;
    }

    private function sameDate(mixed $first, mixed $second): bool
    {
        return $this->dateString($first) === $this->dateString($second);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value, 0, 10);
    }
}
