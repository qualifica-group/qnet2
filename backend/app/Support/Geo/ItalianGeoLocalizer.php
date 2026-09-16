<?php

namespace App\Support\Geo;

use Illuminate\Support\Str;

/**
 * Bridges the Italian geo strings external systems emit to the ENGLISH /
 * anglicized reference dataset (world.sql) qnet ships: `Italia`->`Italy`,
 * `Sicilia`->`Sicily`, the province plate code `NA`->`Naples`, `Napoli`->
 * `Naples`. Reference data, not business logic — it lives in this neutral,
 * shared namespace so EVERY import path consumes it: the migration resolver
 * (App\Migrations\Support\MigrationGeoResolver — companies, operational-sites)
 * AND the generic CSV import resolver (App\Imports\Support\GeoResolver), plus
 * any future import with the same address shape. The matching lives in ONE
 * place.
 *
 * Every method returns the CANONICAL string to match the reference table on
 * (case-insensitively); an input already matching the dataset (`Campania`,
 * `Avellino`) simply passes through trimmed. `province()` is the exception:
 * the legacy value is a 2-letter plate code with no textual overlap with the
 * province name, so an unknown code returns null (unresolvable).
 */
class ItalianGeoLocalizer
{
    /**
     * Country name aliases (normalized IT -> reference EN). Only deltas are
     * listed; anything else passes through.
     *
     * @var array<string, string>
     */
    private const array COUNTRIES = [
        'italia' => 'Italy',
    ];

    /**
     * Region (state) name aliases — only the Italian names that DIFFER from the
     * anglicized dataset (plus the recurring `sicillia` legacy typo). Regions
     * already spelled as in the dataset (Campania, Lazio, Veneto...) pass
     * through untouched.
     *
     * @var array<string, string>
     */
    private const array REGIONS = [
        'emilia romagna' => 'Emilia-Romagna',
        'friuli venezia giulia' => 'Friuli–Venezia Giulia',
        'lombardia' => 'Lombardy',
        'piemonte' => 'Piedmont',
        'puglia' => 'Apulia',
        'sardegna' => 'Sardinia',
        'sicilia' => 'Sicily',
        'sicillia' => 'Sicily',
        'toscana' => 'Tuscany',
        'trentino alto adige' => 'Trentino-South Tyrol',
        "valle d'aosta" => 'Aosta Valley',
    ];

    /**
     * City name aliases (normalized legacy value -> reference spelling). Two
     * kinds: the handful of Italian cities the dataset stores anglicized, and
     * the comuni it spells differently from the legacy `comune` (one word,
     * hyphenated, shortened to the historical place name, or plainly
     * misspelled at the source, like the `Sicillia` typo in REGIONS). Native,
     * identically spelled names (Frattamaggiore, Aversa, Caserta...) pass
     * through.
     *
     * @var array<string, string>
     */
    private const array CITIES = [
        'napoli' => 'Naples',
        'roma' => 'Rome',
        'milano' => 'Milan',
        'torino' => 'Turin',
        'firenze' => 'Florence',
        'genova' => 'Genoa',
        'venezia' => 'Venice',
        'padova' => 'Padua',
        'mantova' => 'Mantua',
        'san nicandro garganico' => 'Sannicandro Garganico',
        "godega di sant'urbano" => 'Godega',
        'cancello ed arnone' => 'Cancello-Arnone',
        // Legacy typo: the record's own province (CA) and postal code (09028)
        // are Sestu's, not Setzu's (South Sardinia, 09029).
        'setsu' => 'Sestu',
    ];

    /**
     * Italian province plate code (targa) -> reference province name. The full
     * standard set; each value is a name actually present in the dataset (AO /
     * Aosta is omitted — the dataset has no such province row).
     *
     * @var array<string, string>
     */
    private const array PROVINCE_CODES = [
        'AG' => 'Agrigento', 'AL' => 'Alessandria', 'AN' => 'Ancona', 'AP' => 'Ascoli Piceno',
        'AQ' => "L'Aquila", 'AR' => 'Arezzo', 'AT' => 'Asti', 'AV' => 'Avellino',
        'BA' => 'Bari', 'BG' => 'Bergamo', 'BI' => 'Biella', 'BL' => 'Belluno',
        'BN' => 'Benevento', 'BO' => 'Bologna', 'BR' => 'Brindisi', 'BS' => 'Brescia',
        'BT' => 'Barletta-Andria-Trani', 'BZ' => 'South Tyrol', 'CA' => 'Cagliari',
        'CB' => 'Campobasso', 'CE' => 'Caserta', 'CH' => 'Chieti', 'CL' => 'Caltanissetta',
        'CN' => 'Cuneo', 'CO' => 'Como', 'CR' => 'Cremona', 'CS' => 'Cosenza',
        'CT' => 'Catania', 'CZ' => 'Catanzaro', 'EN' => 'Enna', 'FC' => 'Forlì-Cesena',
        'FE' => 'Ferrara', 'FG' => 'Foggia', 'FI' => 'Florence', 'FM' => 'Fermo',
        'FR' => 'Frosinone', 'GE' => 'Genoa', 'GO' => 'Gorizia', 'GR' => 'Grosseto',
        'IM' => 'Imperia', 'IS' => 'Isernia', 'KR' => 'Crotone', 'LC' => 'Lecco',
        'LE' => 'Lecce', 'LI' => 'Livorno', 'LO' => 'Lodi', 'LT' => 'Latina',
        'LU' => 'Lucca', 'MB' => 'Monza and Brianza', 'MC' => 'Macerata', 'ME' => 'Messina',
        'MI' => 'Milan', 'MN' => 'Mantua', 'MO' => 'Modena', 'MS' => 'Massa and Carrara',
        'MT' => 'Matera', 'NA' => 'Naples', 'NO' => 'Novara', 'NU' => 'Nuoro',
        'OR' => 'Oristano', 'PA' => 'Palermo', 'PC' => 'Piacenza', 'PD' => 'Padua',
        'PE' => 'Pescara', 'PG' => 'Perugia', 'PI' => 'Pisa', 'PN' => 'Pordenone',
        'PO' => 'Prato', 'PR' => 'Parma', 'PT' => 'Pistoia', 'PU' => 'Pesaro and Urbino',
        'PV' => 'Pavia', 'PZ' => 'Potenza', 'RA' => 'Ravenna', 'RC' => 'Reggio Calabria',
        'RE' => 'Reggio Emilia', 'RG' => 'Ragusa', 'RI' => 'Rieti', 'RM' => 'Rome',
        'RN' => 'Rimini', 'RO' => 'Rovigo', 'SA' => 'Salerno', 'SI' => 'Siena',
        'SO' => 'Sondrio', 'SP' => 'La Spezia', 'SR' => 'Siracusa', 'SS' => 'Sassari',
        'SU' => 'South Sardinia', 'SV' => 'Savona', 'TA' => 'Taranto', 'TE' => 'Teramo',
        'TN' => 'Trentino', 'TO' => 'Turin', 'TP' => 'Trapani', 'TR' => 'Terni',
        'TS' => 'Trieste', 'TV' => 'Treviso', 'UD' => 'Udine', 'VA' => 'Varese',
        'VB' => 'Verbano-Cusio-Ossola', 'VC' => 'Vercelli', 'VE' => 'Venice', 'VI' => 'Vicenza',
        'VR' => 'Verona', 'VT' => 'Viterbo', 'VV' => 'Vibo Valentia',
    ];

    public function country(string $name): string
    {
        return self::COUNTRIES[$this->key($name)] ?? trim($name);
    }

    public function region(string $name): string
    {
        return self::REGIONS[$this->key($name)] ?? trim($name);
    }

    /**
     * The reference province name for a legacy plate code, or null when the
     * code is unknown (the legacy string is a code, never the name itself, so
     * there is no textual fallback to try).
     */
    public function province(string $code): ?string
    {
        return self::PROVINCE_CODES[strtoupper(trim($code))] ?? null;
    }

    /**
     * The reference city name for a legacy `comune` value: the label is first
     * stripped (see cleanCityLabel), then translated if anglicized. Returns null
     * only when the cleaned label is empty (a pure placeholder like "XXX").
     */
    public function city(string $name): ?string
    {
        $cleaned = $this->cleanCityLabel($name);

        if ($cleaned === '') {
            return null;
        }

        return self::CITIES[$this->key($cleaned)] ?? $cleaned;
    }

    /**
     * Strip the site-label noise the legacy `comune` field carries so the bare
     * comune remains: everything from a " - " separator ("Roma - 2",
     * "Viterbo - Sede Temporanea"), any parenthetical ("Palermo (ex?)",
     * "FRATTAMAGGIORE (HQ)") and a trailing site number ("Benevento 1").
     */
    public function cleanCityLabel(string $raw): string
    {
        $value = trim($raw);

        // Step 1: drop everything from the first " - " dash separator.
        $dashPosition = strpos($value, ' - ');
        if ($dashPosition !== false) {
            $value = substr($value, 0, $dashPosition);
        }

        // Step 2: remove parentheticals and any trailing standalone number.
        $value = preg_replace('/\s*\([^)]*\)/', '', $value) ?? $value;
        $value = preg_replace('/\s+\d+$/', '', $value) ?? $value;

        return trim($value);
    }

    private function key(string $value): string
    {
        return Str::lower(trim($value));
    }
}
