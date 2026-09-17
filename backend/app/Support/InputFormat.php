<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ContactTypeEnum;
use App\Support\Fiscal\ItalianTaxCode;
use App\Support\Fiscal\ItalianVatNumber;

/**
 * Single source of truth for the CANONICAL form a user-typed value is stored
 * in (user directive 2026-07-23): the same number typed as `333 12 34 567`,
 * `333-1234567` or `(333) 1234567` must land in the database identically, and
 * the same for names and fiscal identifiers.
 *
 * Distinct from `ContactValueNormalizer`, which normalizes only to COMPARE two
 * values while looking for duplicates (spec 0033/0037) and never touches what
 * is persisted. This one runs at the write boundary (FormRequest
 * `prepareForValidation()` and the inline cell editor), so validation and
 * persistence both see the canonical value.
 *
 * Pure and side-effect free. Mirrored 1:1 by the frontend twin in
 * `frontend/src/lib/formatting/input-format.ts` — keep the two aligned.
 */
final class InputFormat
{
    /**
     * Digits only, keeping an international prefix as a leading `+` (user
     * choice 2026-07-23: no country code is ever ASSUMED, so a number typed
     * without one stays without one). A leading `00` is the same prefix
     * written differently, so it collapses onto `+` — otherwise `0039 333…`
     * and `+39 333…` would remain two spellings of one number.
     */
    public static function phone(string $value): string
    {
        $digits = (string) preg_replace('/[^0-9+]/', '', trim($value));

        // A `+` is only a prefix marker in first position; anywhere else it is
        // typing noise (e.g. `333+444`), never part of the number.
        $hasPlus = str_starts_with($digits, '+');
        $digits = (string) preg_replace('/\D/', '', $digits);

        if (! $hasPlus && str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        return $hasPlus ? '+'.$digits : $digits;
    }

    /**
     * Title case over collapsed whitespace: `  mario   ROSSI ` -> `Mario Rossi`.
     *
     * `mb_convert_case()` alone stops at whitespace and hyphens, so the letter
     * after an apostrophe is re-uppercased on top of it — the Italian surnames
     * `D'Angelo` / `Dell'Acqua` are the common case, not an edge one.
     */
    public static function personName(string $value): string
    {
        $collapsed = (string) preg_replace('/\s+/u', ' ', trim($value));

        $titled = mb_convert_case($collapsed, MB_CASE_TITLE, 'UTF-8');

        return (string) preg_replace_callback(
            '/(?<=\p{L}[\'\x{2019}])\p{L}/u',
            static fn (array $match): string => mb_strtoupper($match[0], 'UTF-8'),
            $titled,
        );
    }

    /** Collapsed whitespace only: a company name's own casing is meaningful (`SRL`, `iGuzzini`). */
    public static function plainText(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($value));
    }

    /** Reuses the fiscal twins' own normalization so a stored code equals the validated one. */
    public static function taxCode(string $value): string
    {
        return ItalianTaxCode::normalize($value);
    }

    /** Eleven digits, stripped of separators and of the optional `IT` country prefix. */
    public static function vatNumber(string $value): string
    {
        return ItalianVatNumber::normalize($value);
    }

    /** Seven alphanumerics, uppercase — same shape rule as the fiscal codes. */
    public static function sdiCode(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value)));
    }

    /**
     * The identity fields of a personal-data card that have a canonical form,
     * in the order they are declared on the card.
     *
     * @var array<int, string>
     */
    public const array IDENTITY_FIELDS = [
        'first_name',
        'last_name',
        'company_name',
        'tax_code',
        'vat_number',
        'sdi_code',
    ];

    /**
     * The canonical form of an identity value, dispatched on its FIELD name —
     * the single mapping shared by the write path (FormatsPersonalDataInput)
     * and by the change-detection guard that has to compare a freshly
     * canonicalized submission against a value persisted before this rule
     * existed (EnforcesFieldPermissions). A field outside the list is returned
     * untouched.
     *
     * @param  bool  $isCompany  a company card's `tax_code` holds the eleven-digit code, whose
     *                           normalization also drops the optional `IT` prefix — dropping it from
     *                           a personal code would corrupt it (a surname CAN encode to `IT...`)
     */
    public static function identityField(string $field, string $value, bool $isCompany = false): string
    {
        return match ($field) {
            'first_name', 'last_name' => self::personName($value),
            'company_name' => self::plainText($value),
            'tax_code' => $isCompany ? self::vatNumber($value) : self::taxCode($value),
            'vat_number' => self::vatNumber($value),
            'sdi_code' => self::sdiCode($value),
            default => $value,
        };
    }

    /**
     * The canonical form of a contact `value`, dispatched on its channel:
     * phone-shaped types collapse to digits, mail-shaped ones to a trimmed
     * lowercase address (a mailbox is case-insensitive in practice), and a
     * website is only trimmed — a URL path IS case-sensitive.
     */
    public static function contactValue(ContactTypeEnum $type, string $value): string
    {
        return match ($type) {
            ContactTypeEnum::Phone, ContactTypeEnum::Fax => self::phone($value),
            ContactTypeEnum::Email, ContactTypeEnum::Pec => mb_strtolower(trim($value)),
            ContactTypeEnum::Website => trim($value),
        };
    }
}
