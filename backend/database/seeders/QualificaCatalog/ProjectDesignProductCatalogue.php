<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "Progettazione" branch, transcribed from the
 * "Funzione Aziendale_ Progettazione" list (user directive 2026-11-03): the
 * eleven rows of "Progettazione interna". Every one is priced 0 and "esente
 * IVA" ("Progettazione APL" gives neither: it takes its siblings'), with no
 * code (the generated "PRD-" one), description, supplier or "Mesi scadenza".
 * Pure data, seeded by BranchProducts under the
 * "Consulenza" typology (ProjectDesignCatalogue::TYPOLOGIES).
 *
 * Names are the list's titles, trimmed; " Progettazione- Autorizzazioni
 * Ministeriali" also loses the stray space after the dash, like its siblings.
 */
final class ProjectDesignProductCatalogue
{
    /**
     * The list's "esente IVA": a 0% rate of its own, matched on its name and
     * created when missing (user decision 2026-11-03), not the "Nessuna" one.
     */
    public const array EXEMPT_VAT_RATE = ['name' => 'Esente IVA', 'rate' => 0.0];

    private const array SPEC = ['price' => 0.0, 'supplier' => null, 'expiration_months' => null, 'vat_rate' => self::EXEMPT_VAT_RATE];

    /**
     * Category name => product name => its price, supplier, "Mesi scadenza" and VAT rate.
     *
     * @var array<string, array<string, array{price: float, supplier: string|null, expiration_months: int|null, vat_rate: array{name: string, rate: float}}>>
     */
    public const array PRODUCTS = [
        ProjectDesignCatalogue::INTERNAL => [
            'Progettazione-Corsi Autofinanziati' => self::SPEC,
            'Progettazione-Accreditamento piattaforme Regionali' => self::SPEC,
            'Progettazione-Accreditamento piattaforme Città Metropolitana/Provincia' => self::SPEC,
            'Progettazione-Accreditamento piattaforme Ministeriali' => self::SPEC,
            'Progettazione-Autorizzazioni Regionali' => self::SPEC,
            'Progettazione-Autorizzazioni Ministeriali' => self::SPEC,
            'Progettazione-ECM' => self::SPEC,
            'Progettazione Sociale' => self::SPEC,
            'Progettazione PA' => self::SPEC,
            'Progettazione Aziendale' => self::SPEC,
            'Progettazione APL' => self::SPEC,
        ],
    ];
}
