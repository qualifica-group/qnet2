<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The subcategories of the "ISO" branch (user directive 2026-10-21, "iso"
 * service list): one per "Categoria" of the list, named "ISO - " + that value,
 * all classification targets that inherit the root's fields and forms. The
 * list's spelling variants are merged (Ecovadis/ECOVADIS, Emas/EMAS,
 * Sa8000/SA8000), "90001+11034" reads "9001+11034", the certifying-body rows
 * ("ENTE_ISO 9001"...) read "Ente 9001", and the list's "ISO" and "STANDARD
 * MULTIPLI" read "Servizi generali" and "Standard multipli" (user decisions
 * 2026-10-21).
 *
 * Pure data, read by BranchCatalogues (tree and targets) and CatalogProducts
 * (typologies); the products are in IsoProductCatalogue and its two siblings.
 */
final class IsoSubcategories
{
    /**
     * For QualificaCatalogSeeder::CATALOG: none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        'ISO - 1090' => [],
        'ISO - 9001' => [],
        'ISO - 9100' => [],
        'ISO - 10854' => [],
        'ISO - 11010' => [],
        'ISO - 11034' => [],
        'ISO - 11381' => [],
        'ISO - 13485' => [],
        'ISO - 13549' => [],
        'ISO - 14001' => [],
        'ISO - 14021' => [],
        'ISO - 14024' => [],
        'ISO - 14064' => [],
        'ISO - 14067' => [],
        'ISO - 16636' => [],
        'ISO - 17020' => [],
        'ISO - 17025' => [],
        'ISO - 20121' => [],
        'ISO - 20400' => [],
        'ISO - 21502' => [],
        'ISO - 22000' => [],
        'ISO - 22005' => [],
        'ISO - 24000' => [],
        'ISO - 27001' => [],
        'ISO - 29001' => [],
        'ISO - 30415' => [],
        'ISO - 37001' => [],
        'ISO - 39001' => [],
        'ISO - 45001' => [],
        'ISO - 50001' => [],
        'ISO - CAM' => [],
        'ISO - Certificazione Bio' => [],
        'ISO - CodexA' => [],
        'ISO - Consulenza Direzionale' => [],
        'ISO - CSR' => [],
        'ISO - D.lgs 231/01' => [],
        'ISO - Ecolabel' => [],
        'ISO - Ecovadis' => [],
        'ISO - EMAS' => [],
        'ISO - Esco' => [],
        'ISO - ESG' => [],
        'ISO - FCC' => [],
        'ISO - Fgas' => [],
        'ISO - GACP' => [],
        'ISO - GAP ANALYSIS' => [],
        'ISO - GMP' => [],
        'ISO - ISCC' => [],
        'ISO - Marcatura Ce' => [],
        'ISO - NAWI' => [],
        'ISO - ODV' => [],
        'ISO - QASER' => [],
        'ISO - RATING DI LEGALITA\'' => [],
        'ISO - RSGI' => [],
        'ISO - SA8000' => [],
        'ISO - UNI PDR 125' => [],
        'ISO - UNI PDR 74' => [],
        'ISO - 3834-2+1090' => [],
        'ISO - 9001+11034' => [],
        'ISO - 9001+14001' => [],
        'ISO - 9001+14001+37001' => [],
        'ISO - 9001+14001+45001' => [],
        'ISO - 9001+14001+45001+37001' => [],
        'ISO - 9001+14001+45001+50001' => [],
        'ISO - 9001+27001' => [],
        'ISO - 9001+27001+27017' => [],
        'ISO - 9001+45001' => [],
        'ISO - 9001+45001+22000' => [],
        'ISO - 9001+45001+27001' => [],
        'ISO - 9001+45001+37001' => [],
        'ISO - 9001+9001RT21' => [],
        'ISO - 9120+9100' => [],
        'ISO - 14001+45001' => [],
        'ISO - 14001+45001+22000' => [],
        'ISO - 14001+50001' => [],
        'ISO - 22000+22005' => [],
        'ISO - 27017+27018' => [],
        'ISO - 37001+27001+39001+UNI PDR 125' => [],
        'ISO - 37001+50001' => [],
        'ISO - ISO 18295-1 + ISO 18295-2' => [],
        'ISO - PIF+CPSR' => [],
        'ISO - Ente 14001' => [],
        'ISO - Ente 22000' => [],
        'ISO - Ente 27001' => [],
        'ISO - Ente 45001' => [],
        'ISO - Ente 9001' => [],
        'ISO - Ente 9001+14001+45001' => [],
        'ISO - Ente F-GAS' => [],
        'ISO - Servizi generali' => [],
        'ISO - Standard multipli' => [],
    ];

    /**
     * Every subcategory, for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        'ISO - 1090',
        'ISO - 9001',
        'ISO - 9100',
        'ISO - 10854',
        'ISO - 11010',
        'ISO - 11034',
        'ISO - 11381',
        'ISO - 13485',
        'ISO - 13549',
        'ISO - 14001',
        'ISO - 14021',
        'ISO - 14024',
        'ISO - 14064',
        'ISO - 14067',
        'ISO - 16636',
        'ISO - 17020',
        'ISO - 17025',
        'ISO - 20121',
        'ISO - 20400',
        'ISO - 21502',
        'ISO - 22000',
        'ISO - 22005',
        'ISO - 24000',
        'ISO - 27001',
        'ISO - 29001',
        'ISO - 30415',
        'ISO - 37001',
        'ISO - 39001',
        'ISO - 45001',
        'ISO - 50001',
        'ISO - CAM',
        'ISO - Certificazione Bio',
        'ISO - CodexA',
        'ISO - Consulenza Direzionale',
        'ISO - CSR',
        'ISO - D.lgs 231/01',
        'ISO - Ecolabel',
        'ISO - Ecovadis',
        'ISO - EMAS',
        'ISO - Esco',
        'ISO - ESG',
        'ISO - FCC',
        'ISO - Fgas',
        'ISO - GACP',
        'ISO - GAP ANALYSIS',
        'ISO - GMP',
        'ISO - ISCC',
        'ISO - Marcatura Ce',
        'ISO - NAWI',
        'ISO - ODV',
        'ISO - QASER',
        'ISO - RATING DI LEGALITA\'',
        'ISO - RSGI',
        'ISO - SA8000',
        'ISO - UNI PDR 125',
        'ISO - UNI PDR 74',
        'ISO - 3834-2+1090',
        'ISO - 9001+11034',
        'ISO - 9001+14001',
        'ISO - 9001+14001+37001',
        'ISO - 9001+14001+45001',
        'ISO - 9001+14001+45001+37001',
        'ISO - 9001+14001+45001+50001',
        'ISO - 9001+27001',
        'ISO - 9001+27001+27017',
        'ISO - 9001+45001',
        'ISO - 9001+45001+22000',
        'ISO - 9001+45001+27001',
        'ISO - 9001+45001+37001',
        'ISO - 9001+9001RT21',
        'ISO - 9120+9100',
        'ISO - 14001+45001',
        'ISO - 14001+45001+22000',
        'ISO - 14001+50001',
        'ISO - 22000+22005',
        'ISO - 27017+27018',
        'ISO - 37001+27001+39001+UNI PDR 125',
        'ISO - 37001+50001',
        'ISO - ISO 18295-1 + ISO 18295-2',
        'ISO - PIF+CPSR',
        'ISO - Ente 14001',
        'ISO - Ente 22000',
        'ISO - Ente 27001',
        'ISO - Ente 45001',
        'ISO - Ente 9001',
        'ISO - Ente 9001+14001+45001',
        'ISO - Ente F-GAS',
        'ISO - Servizi generali',
        'ISO - Standard multipli',
    ];

    /**
     * The certifying-body lines, whose products are bought from the body:
     * filed under the "Ente" typology, the rest under "Consulenza".
     *
     * @var list<string>
     */
    private const array INSTITUTION_CATEGORIES = [
        'ISO - Ente 14001',
        'ISO - Ente 22000',
        'ISO - Ente 27001',
        'ISO - Ente 45001',
        'ISO - Ente 9001',
        'ISO - Ente 9001+14001+45001',
        'ISO - Ente F-GAS',
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder), for BranchProducts.
     *
     * @return array<string, string>
     */
    public static function typologies(): array
    {
        return array_merge(
            array_fill_keys(self::TARGET_CATEGORIES, 'consultancy'),
            array_fill_keys(self::INSTITUTION_CATEGORIES, 'institution'),
        );
    }
}
