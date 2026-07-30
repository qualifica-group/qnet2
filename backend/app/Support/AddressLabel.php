<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Address;

/**
 * Shared composition of a printable, DISPLAY-ready address (spec 0069, D-5):
 * no helper in the repo composed a full postal address before this one (the
 * only precedent, App\Support\OperationalSiteLabel, only ever needed
 * "{line1} - {city}"). Feeds the `client`/`company`/`company_site` categories
 * of the document-layouts variable catalogue
 * (App\Services\DocumentLayouts\DocumentLayoutVariableCatalog) and, later,
 * their resolver (spec 0070).
 *
 * Same contract as OperationalSiteLabel: NEVER queries the database — the
 * caller must eager-load `city`, `province`, `state` (postal_code/line1/line2
 * are plain columns). Geo names are localized to Italian with
 * App\Support\Geo\GeoNameLocalizer::toItalian() (via each geo model's own
 * `localizedName()`, same as OperationalSiteLabel).
 *
 * The 14 existing call sites that compose an address by hand are NOT migrated
 * to this helper (blast-radius constraint, D-5) — only new code (this spec)
 * consumes it.
 */
final class AddressLabel
{
    /**
     * A single printable line, e.g. "Via Roma 10 - 20100 Milano - Lombardia".
     * Empty string for a null address. Never leaves a dangling separator when
     * a part is missing (e.g. only `line1` set returns exactly `line1`).
     */
    public static function singleLine(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        return implode(' - ', self::parts($address));
    }

    /**
     * The same parts as singleLine(), one per array entry, in the declared
     * order (street, then locality, then province, then state, then
     * country) — empty parts are already excluded, so every entry is
     * guaranteed non-empty. Empty array for a null address.
     *
     * @return array<int, string>
     */
    public static function multiLine(?Address $address): array
    {
        if ($address === null) {
            return [];
        }

        return self::parts($address);
    }

    /**
     * @return array<int, string>
     */
    private static function parts(Address $address): array
    {
        return array_values(array_filter([
            self::streetLine($address),
            self::localityLine($address),
            $address->province?->localizedName(),
            $address->state?->localizedName(),
            $address->country?->localizedName(),
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * `line1` and `line2` joined, e.g. "Via Roma 10, Interno 4". `line2` is
     * dropped entirely when unset, so no trailing/leading comma ever appears.
     */
    private static function streetLine(Address $address): ?string
    {
        $street = implode(', ', array_filter([$address->line1, $address->line2], static fn (?string $part): bool => filled($part)));

        return $street === '' ? null : $street;
    }

    /**
     * `postal_code` and the localized city name joined by a space, e.g.
     * "20100 Milano". Either half may be missing; the other still renders
     * alone with no dangling space.
     */
    private static function localityLine(Address $address): ?string
    {
        $city = $address->city?->localizedName();

        $locality = trim(implode(' ', array_filter([$address->postal_code, $city], static fn (?string $part): bool => filled($part))));

        return $locality === '' ? null : $locality;
    }
}
