<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Formal IBAN check (ISO 13616, spec 0189 D-5): country code + 2 check digits +
 * alphanumeric BBAN, 15-34 characters, and the mod-97 checksum, for any country.
 * Expects the NORMALIZED value (see normalize()); a blank value passes —
 * whether the field is required is the FormRequest's call.
 */
final class ValidIban implements ValidationRule
{
    private const int MIN_LENGTH = 15;

    private const int MAX_LENGTH = 34;

    private const int MODULUS = 97;

    /** Uppercase and strip every non-alphanumeric separator (spaces, dashes). */
    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    public static function isValid(string $iban): bool
    {
        $length = strlen($iban);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || ! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        return self::mod97(substr($iban, 4).substr($iban, 0, 4)) === 1;
    }

    /**
     * mod-97 over the letter-to-number expansion (A=10 .. Z=35), computed in
     * chunks so it never overflows an int.
     */
    public static function mod97(string $rearranged): int
    {
        $digits = '';

        foreach (str_split($rearranged) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % self::MODULUS;
        }

        return $remainder;
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
            $fail(__('The IBAN is not valid.'));
        }
    }
}
