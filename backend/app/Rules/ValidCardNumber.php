<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Payment card number check (spec 0189): 12-19 digits and a valid Luhn
 * checksum. Expects the NORMALIZED value (see normalize()); a blank value
 * passes — whether the field is required is the FormRequest's call.
 */
final class ValidCardNumber implements ValidationRule
{
    private const int MIN_DIGITS = 12;

    private const int MAX_DIGITS = 19;

    /** Strip spaces and dashes, the only separators cards are printed with. */
    public static function normalize(string $value): string
    {
        return str_replace([' ', '-'], '', $value);
    }

    public static function isValid(string $number): bool
    {
        $length = strlen($number);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS || ! ctype_digit($number)) {
            return false;
        }

        return self::luhnSum($number) % 10 === 0;
    }

    private static function luhnSum(string $number): int
    {
        $sum = 0;

        foreach (array_reverse(str_split($number)) as $index => $char) {
            $digit = (int) $char;

            if ($index % 2 === 1) {
                $digit *= 2;
                $digit -= $digit > 9 ? 9 : 0;
            }

            $sum += $digit;
        }

        return $sum;
    }

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! self::isValid($value)) {
            $fail(__('The card number is not valid.'));
        }
    }
}
