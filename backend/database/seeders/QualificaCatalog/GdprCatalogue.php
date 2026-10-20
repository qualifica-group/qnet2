<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "GDPR" branch of the catalogue (user directive 2026-10-20, "gestione
 * richieste gdpr" sheet and "SERVIZI GDPR" list): a root of its own, linked to
 * the "Privacy" business function of the organisation chart, whose two
 * subcategories are the sheet's product lines prefixed with the branch like
 * "Sicurezza" ("GDPR - ..."), each a classification target hosting its
 * products (GdprProductCatalogue). Pure data, read by QualificaCatalogSeeder
 * (tree and offer fields), QualificaQuoteLayoutSeeder (offer form),
 * CatalogProducts (products), CatalogRootRules and
 * QualificaBusinessFunctionLinkSeeder.
 *
 * Worked like "Consulenza", every product under the "Consulenza" typology,
 * with the default working states and no Commessa field. The offer fields sit
 * on the root, inherited by both lines with the root's form.
 *
 * `code` is the English identifier; `name` is the user-facing label, kept in
 * its original language.
 */
final class GdprCatalogue
{
    public const string ROOT = 'GDPR';

    public const string BUSINESS_FUNCTION = 'Privacy';

    public const string GDPR = 'GDPR - GDPR';

    public const string DPO_APPOINTMENT = 'GDPR - Nomina DPO';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::GDPR,
        self::DPO_APPOINTMENT,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::GDPR => [],
        self::DPO_APPOINTMENT => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::GDPR => 'consultancy',
        self::DPO_APPOINTMENT => 'consultancy',
    ];

    /**
     * The OFFERTA-context fields, in the sheet's order ("Informazioni
     * flessibili").
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    public const array QUOTE_ATTRIBUTES = [
        ['code' => 'gdpr_employee_count', 'name' => 'Numero dipendenti', 'type' => 'integer'],
        ['code' => 'gdpr_business_activity', 'name' => 'Attività svolta', 'type' => 'textarea'],
        ['code' => 'gdpr_video_surveillance', 'name' => 'L\'azienda ha un sistema di video-sorveglianza?', 'type' => 'boolean'],
        ['code' => 'gdpr_video_surveillance_sites', 'name' => 'Se sì, in quante sedi?', 'type' => 'text'],
        ['code' => 'gdpr_checklist', 'name' => 'Check list GDPR', 'type' => 'boolean'],
        ['code' => 'gdpr_notes', 'name' => 'Note', 'type' => 'textarea'],
    ];

    /**
     * The offer form: the sheet's single block.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array QUOTE_SECTIONS = [
        ['gdpr-request', 'GDPR', [
            ['gdpr_employee_count'],
            ['gdpr_business_activity'],
            ['gdpr_video_surveillance', 'gdpr_video_surveillance_sites'],
            ['gdpr_checklist'],
            ['gdpr_notes'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Dati dell\'azienda per la richiesta GDPR.']],
    ];
}
