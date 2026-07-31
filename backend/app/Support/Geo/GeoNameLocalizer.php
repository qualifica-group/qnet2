<?php

namespace App\Support\Geo;

use Illuminate\Support\Str;

/**
 * DISPLAY-side counterpart of ItalianGeoLocalizer. The reference dataset
 * (world.sql) stores Italian geo anglicized (`Italy`, `Lombardy`, `Naples`);
 * the DB column and every SQL match stay English (imports, filters), but the
 * UI must read Italian. This maps the reference ENGLISH name to the Italian
 * DISPLAY name for the handful of Italy rows the dataset anglicizes; every
 * other name (already Italian: `Campania`, `Avellino`, or a foreign name) is
 * returned untouched.
 *
 * Only Italy is localized (scope decision 2026-07-17): the dataset already
 * stores most Italian names natively, so the delta is small and is the exact
 * inverse of ItalianGeoLocalizer's import aliases plus the anglicized province
 * names. Stateless pure lookup (static), so model accessors and API Resources
 * — neither of which get constructor injection — consume it without wiring.
 *
 * The map is a bijection (each English key maps to one Italian value, both
 * unique), so `toEnglish()` reverses `toItalian()` losslessly — this is what keeps the
 * set-filter consistent: the option list is shown in Italian, and a selected
 * Italian value is mapped back to the English name the DB matches on.
 */
final class GeoNameLocalizer
{
    /**
     * Reference ENGLISH name -> Italian DISPLAY name. Only the Italy rows the
     * dataset stores anglicized are listed; anything else passes through. The
     * same English string carries the same translation whether it surfaces as
     * a region, province or city (e.g. `Florence`/`Naples` exist as both a
     * province and a city), so one flat map is unambiguous.
     *
     * @var array<string, string>
     */
    private const array TO_ITALIAN = [
        // Country
        'Italy' => 'Italia',
        // Regions (states)
        'Aosta Valley' => "Valle d'Aosta",
        'Apulia' => 'Puglia',
        'Friuli–Venezia Giulia' => 'Friuli-Venezia Giulia',
        'Lombardy' => 'Lombardia',
        'Piedmont' => 'Piemonte',
        'Sardinia' => 'Sardegna',
        'Sicily' => 'Sicilia',
        'Trentino-South Tyrol' => 'Trentino-Alto Adige',
        'Tuscany' => 'Toscana',
        // Provinces (+ the cities sharing the same anglicized string)
        'Florence' => 'Firenze',
        'Genoa' => 'Genova',
        'Mantua' => 'Mantova',
        'Massa and Carrara' => 'Massa-Carrara',
        'Milan' => 'Milano',
        'Monza and Brianza' => 'Monza e Brianza',
        'Naples' => 'Napoli',
        'Padua' => 'Padova',
        'Pesaro and Urbino' => 'Pesaro e Urbino',
        'Rome' => 'Roma',
        'South Sardinia' => 'Sud Sardegna',
        'South Tyrol' => 'Bolzano',
        'Trentino' => 'Trento',
        'Turin' => 'Torino',
        'Venice' => 'Venezia',
    ];

    /**
     * The Italian display name for a reference ENGLISH name, or the input
     * unchanged when the row is not one of the anglicized Italy deltas (null
     * passes through as null so nullable relations stay nullable).
     */
    public static function toItalian(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return self::TO_ITALIAN[$name] ?? $name;
    }

    /**
     * The reference ENGLISH name for an Italian display name — the inverse of
     * toItalian(), used to translate a set-filter value the UI submitted in
     * Italian back to the English name the DB column stores. An unmapped value
     * (already English, or a foreign name) passes through unchanged.
     */
    public static function toEnglish(string $name): string
    {
        return self::reverse()[$name] ?? $name;
    }

    /**
     * Reverse a list of set-filter values (Italian display names) to the
     * English names the DB matches on. Order preserved; passthrough per value.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public static function toEnglishValues(array $names): array
    {
        return array_map(self::toEnglish(...), $names);
    }

    /**
     * The DB names a set of Italian filter values may match: each value AND
     * its reversed English name, de-duplicated. Matching on BOTH keeps the
     * filter correct whether the reference row is stored English (`Milan`, the
     * world.sql norm — matched via the reverse) or already Italian (`Milano`,
     * as some seeded/imported rows are — matched via the original value).
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public static function filterMatchNames(array $names): array
    {
        return array_values(array_unique(array_merge($names, self::toEnglishValues($names))));
    }

    /**
     * The reference ENGLISH names whose Italian display CONTAINS $needle
     * (case-insensitive) — the extra rows a quick-search typed in Italian must
     * reach, since the DB column is English. e.g. `napo` -> `['Naples']`,
     * letting "napoli" find the row stored as `Naples`. Empty when nothing
     * matches (the caller keeps its plain English LIKE regardless).
     *
     * @return array<int, string>
     */
    public static function englishNamesMatching(string $needle): array
    {
        $trimmed = trim($needle);

        if ($trimmed === '') {
            return [];
        }

        $matches = [];

        foreach (self::TO_ITALIAN as $english => $italian) {
            if (Str::contains($italian, $trimmed, ignoreCase: true)) {
                $matches[] = $english;
            }
        }

        return $matches;
    }

    /**
     * The reference ENGLISH names whose Italian display STARTS WITH $needle
     * (case-insensitive) — the prefix-search counterpart of
     * englishNamesMatching(), for the geo cascade city lookup, which is a
     * `name LIKE 'needle%'` prefix match rather than a quick-search "contains".
     * Keeping the two in step matters: a contains-match here would let `poli`
     * surface `Napoli` in a select whose every other hit is a prefix.
     *
     * @return array<int, string>
     */
    public static function englishNamesStartingWith(string $needle): array
    {
        $trimmed = trim($needle);

        if ($trimmed === '') {
            return [];
        }

        $lowered = Str::lower($trimmed);
        $matches = [];

        foreach (self::TO_ITALIAN as $english => $italian) {
            if (str_starts_with(Str::lower($italian), $lowered)) {
                $matches[] = $english;
            }
        }

        return $matches;
    }

    /**
     * @return array<string, string>
     */
    private static function reverse(): array
    {
        return array_flip(self::TO_ITALIAN);
    }
}
