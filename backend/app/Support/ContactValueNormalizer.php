<?php

namespace App\Support;

use App\Enums\ContactTypeEnum;

/**
 * Single source of truth for how a contact `value`/`tax_code` is normalized
 * before two of them are compared for equality (spec 0037): email
 * lowercase+trim, phone/mobile/fax/pec/website digits+`+` only, tax_code
 * uppercase+trim. Extracted from `LeadDuplicateMatcher` (spec 0033/0036) so
 * the leads-import duplicate check and `IdentityDuplicateFinder` (spec 0037)
 * share the EXACT same semantics — never fork.
 */
final class ContactValueNormalizer
{
    public static function contact(ContactTypeEnum $type, string $value): string
    {
        $trimmed = trim($value);

        return $type === ContactTypeEnum::Email
            ? mb_strtolower($trimmed)
            : (string) preg_replace('/[^0-9+]/', '', $trimmed);
    }

    public static function taxCode(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
