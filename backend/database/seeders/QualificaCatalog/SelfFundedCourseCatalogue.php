<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's self-funded ("Autofinanziato") course catalogue: the courses
 * sold directly to the learner, each with its list price. Pure data, like
 * TrainingCourseCatalogue — the seeder holds the logic, this file holds the
 * rows.
 *
 * Split per region like the GOL courses (user directive 2026-09-25, source
 * "Repertorio Corsi_Autofinanziati", one sheet per region): "Autofinanziato"
 * is the CONTAINER, each `Autofinanziato - <Regione>` leaf holds its courses.
 * Unlike GOL these carry a real `price`. Duration and delivery mode are the
 * OFFERTA's (CourseDataAttributeCatalogue, user directive 2026-09-08), so the
 * rows carry neither.
 *
 * A sheet lists the same course once per site (Sicilia: nine sites, Lazio:
 * three); a course is ONE product per region, never one per site (user
 * directive 2026-09-25). Where the sites quote different prices the row takes
 * the lowest one, also the most frequent: ASO Sicilia (1.200 / 1.400 / 1.600
 * / 1.700) and ASACOM Sicilia ("1.200 / 1.300").
 *
 * `plus_vat` marks a price quoted "+ iva": the price is net and the product
 * carries the 22% rate (VAT_RATE). The other prices carry no rate.
 *
 * Course names are user-facing domain values, kept as the sheet spells them
 * (an in-cell line break folded to " - "), and are the natural key for the
 * idempotent per-category upsert. The Campania names predate the per-region
 * split and are kept byte for byte, so the products seeded then are moved
 * onto their region instead of duplicated (CatalogProducts).
 */
final class SelfFundedCourseCatalogue
{
    /**
     * The container the regional leaves hang under — a node of
     * QualificaCatalogSeeder::CATALOG, bound by identity so a rename there
     * breaks loudly here.
     */
    public const string CATEGORY = 'Autofinanziato';

    /**
     * The rate a `plus_vat` course carries, matched on the percentage so an
     * operator's own 22% row is reused whatever its name; VAT_RATE_NAME only
     * names the row created when none exists (DemoVatRateSeeder's spelling).
     */
    public const float VAT_RATE = 22.0;

    public const string VAT_RATE_NAME = 'IVA 22%';

    /**
     * Regional leaf => its courses.
     *
     * @var array<string, list<array{name: string, price: float, plus_vat: bool}>>
     */
    public const array COURSES = [
        'Autofinanziato - Campania' => [
            ['name' => 'OSS - Operatore Socio Sanitario', 'price' => 1900.0, 'plus_vat' => false],
            ['name' => 'ASO - Assistente Studio Odontoiatrico', 'price' => 1500.0, 'plus_vat' => false],
            ['name' => 'ASACOM - Assistente all\'Autonomia e alla Comunicazione', 'price' => 1000.0, 'plus_vat' => false],
            ['name' => 'OPI - Operatore per l\'Infanzia', 'price' => 700.0, 'plus_vat' => false],
            ['name' => 'OAC - Operatore Contabile Amministrativo', 'price' => 700.0, 'plus_vat' => false],
            ['name' => 'OSA - Operatore Socio Assistenziale', 'price' => 700.0, 'plus_vat' => false],
            ['name' => 'Tecnico del Comportamento ABA - Online', 'price' => 250.0, 'plus_vat' => false],
            ['name' => 'EIPASS', 'price' => 230.0, 'plus_vat' => true],
            ['name' => 'PEKIT Expert', 'price' => 150.0, 'plus_vat' => true],
            ['name' => 'Aggiornamento ASO', 'price' => 130.0, 'plus_vat' => false],
        ],
        'Autofinanziato - Lazio' => [
            ['name' => 'Tecnico del comportamento Aba - Analisi comportamentale applicata', 'price' => 650.0, 'plus_vat' => true],
        ],
        'Autofinanziato - Lombardia' => [
            ['name' => 'Sarto', 'price' => 400.0, 'plus_vat' => false],
            ['name' => 'Meditazione e Respirazione Consapevole', 'price' => 350.0, 'plus_vat' => false],
        ],
        'Autofinanziato - Sicilia' => [
            ['name' => 'ASACOM (Assistente all\'autonomia ed alla comunicazione dei disabili)', 'price' => 1200.0, 'plus_vat' => false],
            ['name' => 'OSA (Operatore Socio Assistenziale)', 'price' => 1200.0, 'plus_vat' => false],
            ['name' => 'ASO (Assistente studio odontoiatrico)', 'price' => 1200.0, 'plus_vat' => false],
            ['name' => 'Addetto amministrativo segretariale', 'price' => 1000.0, 'plus_vat' => false],
            ['name' => 'Assistente alla struttura educativa', 'price' => 700.0, 'plus_vat' => false],
            ['name' => 'Security', 'price' => 400.0, 'plus_vat' => false],
        ],
    ];
}
