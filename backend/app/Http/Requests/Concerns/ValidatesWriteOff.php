<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ResidualMode;
use App\Enums\WriteOffReason;
use App\Support\Amounts\LineAmountCalculator;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the collection and payment requests for the write-off
 * residual mode (spec 0361): a zero amount and the write-off fields only make
 * sense together, and a write-off needs a partial amount.
 */
trait ValidatesWriteOff
{
    private const int WRITE_OFF_NOTE_MAX = 255;

    /**
     * Rule for the amount field: zero is allowed only with the write-off mode.
     */
    private function zeroOnlyWithWriteOff(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && bccomp(app(LineAmountCalculator::class)->roundToCents($value), '0', 2) === 0
                && $this->input('residual_mode') !== ResidualMode::WriteOff->value) {
                $fail('The amount must be greater than zero, unless the residual is written off.');
            }
        };
    }

    /**
     * Rule for residual_mode: a write-off must leave a residual to write off.
     */
    private function writeOffNeedsPartial(string $amountField, mixed $installmentAmount): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($amountField, $installmentAmount): void {
            if ($value === ResidualMode::WriteOff->value && is_numeric($this->input($amountField))
                && ! app(LineAmountCalculator::class)->isPartial($this->input($amountField), $installmentAmount)) {
                $fail('A write-off needs an amount lower than the installment.');
            }
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function writeOffRules(): array
    {
        $isWriteOff = fn (): bool => $this->input('residual_mode') === ResidualMode::WriteOff->value;

        return [
            'write_off_reason' => [Rule::requiredIf($isWriteOff), 'nullable', Rule::enum(WriteOffReason::class), 'prohibited_unless:residual_mode,'.ResidualMode::WriteOff->value],
            'write_off_note' => ['nullable', 'string', 'max:'.self::WRITE_OFF_NOTE_MAX, 'prohibited_unless:residual_mode,'.ResidualMode::WriteOff->value],
        ];
    }
}
