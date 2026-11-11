<?php

declare(strict_types=1);

namespace App\Support\Fiscal;

/**
 * A foreign VAT number or tax identifier, recognized by the two-letter country
 * prefix it is written with (`DE123456789`, `FR12345678901`, `CHE123456789`):
 * the VIES convention for EU VAT numbers, extended to any foreign code. Foreign
 * formats are too many to verify, so only the shape is checked: prefix (never
 * `IT`, whose codes keep their own algorithms) followed by 2 to 16
 * alphanumerics.
 *
 * Pure and side-effect free. Mirrored 1:1 by the frontend twin in
 * `frontend/src/lib/fiscal/foreign-fiscal-code.ts`.
 */
final class ForeignFiscalCode
{
    private const string PATTERN = '/^(?!IT)[A-Z]{2}[A-Z0-9]{2,16}$/';

    /**
     * Six leading letters are the surname/name triples of an Italian personal
     * code: such a value is a mistyped Italian code, never a foreign one.
     */
    private const string ITALIAN_PERSONAL_SHAPE = '/^[A-Z]{6}/';

    /** Uppercase, stripped of every separator the user may have typed. */
    public static function normalize(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value)));
    }

    public static function isValid(string $value): bool
    {
        $code = self::normalize($value);

        return preg_match(self::PATTERN, $code) === 1
            && preg_match(self::ITALIAN_PERSONAL_SHAPE, $code) !== 1;
    }
}
