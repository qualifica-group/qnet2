<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The OFFERTA-context fields of the APL extracurricular internships,
 * transcribed from the "Campi Misure APL" sheet (user directive 2026-10-02,
 * which REPLACES the Orientamento/SFL GOL sheet of 2026-10-01: "Orientamento
 * Specialistico" keeps no field of its own). Pure data, like
 * ECampusAttributeCatalogue — QualificaCatalogSeeder creates and assigns them
 * on the CATEGORY, QualificaQuoteLayoutSeeder lays them out in the SECTIONS
 * below.
 *
 * They are the category's WHOLE offer form: it is cut off the "APL" root
 * (CategoryInheritanceRules), which on an imported database carries the
 * Ricerca & Selezione job-description fields.
 *
 * NOT FIELDS, by design: the sheet's "Utente" is the opportunity's registry;
 * "Commerciale" and "Segnalatore" are the offer's own `commercial_id` and
 * `reporter_id`; "Stato pratica" is the working state
 * (AplInternshipWorkflowStatusCatalogue). Out of scope (user directive
 * 2026-10-02): "Tutor", "Note", everything about the host entity — the
 * "Soggetto ospitante" pick list, its registry, the company tutor — the
 * trainee's own data and the monthly-register automation.
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class AplInternshipAttributeCatalogue
{
    /**
     * Seeded under "APL" so it exists on a clean database too; on an imported
     * one ProductCategoriesSource adopts it by this exact name.
     */
    public const string CATEGORY = 'Tirocini extracurriculari privati';

    /**
     * The decree and reporting references, shared with the apprenticeship
     * practices (ApprenticeshipAttributeCatalogue): one attribute per concept
     * across the APL branch, so the values filter and report together.
     *
     * @var array{code: string, name: string, type: string, options: list<array{value: string, label: string}>}
     */
    public const array DECREE_STATUS = ['code' => 'decree_status', 'name' => 'Decreto', 'type' => 'enum', 'options' => [
        ['value' => 'lost', 'label' => 'Persa'],
        ['value' => 'sent', 'label' => 'Inviata'],
        ['value' => 'accepted', 'label' => 'Accolta'],
        ['value' => 'paid', 'label' => 'Pagata'],
    ]];

    /** @var array{code: string, name: string, type: string} */
    public const array DECREE_ID = ['code' => 'decree_id', 'name' => 'ID decreto', 'type' => 'text'];

    /** @var array{code: string, name: string, type: string} */
    public const array REPORTING_ID = ['code' => 'reporting_id', 'name' => 'ID rendicontazione', 'type' => 'text'];

    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>
     */
    public const array ATTRIBUTES = [
        ['code' => 'practice_start_date', 'name' => 'Data inizio', 'type' => 'date'],
        ['code' => 'practice_end_date', 'name' => 'Data fine', 'type' => 'date'],
        ['code' => 'registers_status', 'name' => 'Registri', 'type' => 'enum', 'options' => [
            ['value' => 'entered', 'label' => 'Inserito'],
            ['value' => 'not_entered', 'label' => 'Non inserito'],
        ]],
        self::DECREE_STATUS,
        ['code' => 'practice_number', 'name' => 'Numero pratica', 'type' => 'text'],
        self::REPORTING_ID,
        self::DECREE_ID,
    ];

    /**
     * The offer form in the sheet's reading order: the header the operator
     * checks first (registers, decree and the internship's dates),
     * highlighted; then the practice references.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['apl-internship-status', 'Stato pratica', [
            ['registers_status', 'decree_status'],
            ['practice_start_date', 'practice_end_date'],
        ], ['variant' => LayoutSectionVariant::Highlighted, 'columns' => 2, 'description' => 'Registri, decreto e periodo del tirocinio.']],
        ['apl-internship-data', 'Dati pratica', [
            ['practice_number'],
            ['reporting_id', 'decree_id'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Numero pratica e riferimenti di rendicontazione.']],
    ];
}
