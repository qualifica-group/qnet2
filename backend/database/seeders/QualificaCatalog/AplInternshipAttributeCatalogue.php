<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The OFFERTA-context fields of the APL extracurricular internships,
 * transcribed from the "Campi Misure APL" sheet (user directive 2026-10-02,
 * which REPLACES the Orientamento/SFL GOL sheet of 2026-10-01: "Orientamento
 * Specialistico" keeps no field of its own), revised by the user directive
 * 2026-10-07. Pure data, like ECampusAttributeCatalogue — QualificaCatalogSeeder
 * creates and assigns them on the CATEGORY, QualificaQuoteLayoutSeeder lays
 * them out in the SECTIONS below.
 *
 * They are the category's WHOLE offer form: it is cut off the "APL" root
 * (CategoryInheritanceRules), so a field assigned on the root never reaches it.
 *
 * "Soggetto ospitante" is a field of its own, a link to the host entity's
 * registry (user directive 2026-10-07, as for the orientation "Anagrafica
 * Utente"): for the GOL programme the host is not the customer, so it cannot
 * be read off the opportunity.
 *
 * NOT FIELDS, by design: the trainee is the customer registry linked to the
 * offer; "Commerciale" and "Segnalatore" are the offer's own `commercial_id`
 * and `reporter_id`; "Stato pratica" is the working state
 * (AplInternshipWorkflowStatusCatalogue). Out of scope: "Tutor" (user
 * directive 2026-10-07), "Note" and the monthly-register automation.
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class AplInternshipAttributeCatalogue
{
    /**
     * Seeded under "APL"; it replaces the legacy "Tirocini extracurriculari
     * privati", which the import files under "APL old" and whose products it
     * moves here (LegacyAplBranch, user directive 2026-10-05).
     */
    public const string CATEGORY = 'Tirocinio';

    /**
     * The decree and reporting references, shared across the APL practices
     * (ApprenticeshipAttributeCatalogue, AplOrientationAttributeCatalogue):
     * one attribute per concept across the APL branch, so the values filter
     * and report together. The internship itself no longer carries
     * DECREE_STATUS (see RETIRED_ATTRIBUTES).
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

    /** @var array{code: string, name: string, type: string} */
    public const array PRACTICE_END_DATE = ['code' => 'practice_end_date', 'name' => 'Data fine', 'type' => 'date'];

    /**
     * @var list<array{code: string, name: string, type: string, relation_target?: array<string, mixed>}>
     */
    public const array ATTRIBUTES = [
        self::REPORTING_ID,
        self::DECREE_ID,
        ['code' => 'internship_type', 'name' => 'Tipologia tirocinio', 'type' => 'text'],
        ['code' => 'vacancy_code', 'name' => 'Codice Vacancy', 'type' => 'text'],
        ['code' => 'inail_position_number', 'name' => 'Posizione INAIL n.', 'type' => 'text'],
        ['code' => 'liability_policy_number', 'name' => 'Polizza di responsabilità civile n.', 'type' => 'text'],
        ['code' => 'insurance_company', 'name' => 'Compagnia assicuratrice', 'type' => 'text'],
        ['code' => 'practice_start_date', 'name' => 'Data inizio', 'type' => 'date'],
        self::PRACTICE_END_DATE,
        ['code' => 'host_registry', 'name' => 'Soggetto ospitante', 'type' => 'relation', 'relation_target' => [
            'entity_type' => 'registries',
            'cardinality' => 'one',
            'for_select_resource' => 'registries',
        ]],
    ];

    /**
     * The codes the 2026-10-02 revision assigned and the 2026-10-07 directive
     * took off the internship: withdrawn from THIS category only, because
     * "decree_status" stays on the apprenticeship and orientation forms.
     *
     * @var list<string>
     */
    public const array RETIRED_ATTRIBUTES = ['registers_status', 'decree_status', 'practice_number'];

    /**
     * The offer form in the sheet's reading order: the header references, the
     * internship's data (type, vacancy, insurance cover, period), then the host
     * entity. Every section white (user
     * directive 2026-10-05).
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['apl-internship-header', 'Testata', [
            ['reporting_id', 'decree_id'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Riferimenti di rendicontazione e decreto della pratica.']],
        ['apl-internship-data', 'Dati tirocinio', [
            ['internship_type', 'vacancy_code'],
            ['inail_position_number', 'liability_policy_number'],
            ['insurance_company'],
            ['practice_start_date', 'practice_end_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Tipologia, vacancy, coperture assicurative e periodo del tirocinio.']],
        ['apl-internship-host', 'Soggetto ospitante', [
            ['host_registry'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Anagrafica del soggetto ospitante: per il programma GOL non è il cliente.']],
    ];

    /**
     * The form the 2026-10-02 revision seeded: QualificaQuoteLayoutSeeder
     * recognises it — once the retirement has stripped RETIRED_ATTRIBUTES out
     * of it — and recomposes it, or an installation already seeded would get
     * the new fields only in the synthesized "other information" section.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array PREVIOUS_SECTIONS = [
        ['apl-internship-status', 'Stato pratica', [
            ['registers_status', 'decree_status'],
            ['practice_start_date', 'practice_end_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Registri, decreto e periodo del tirocinio.']],
        ['apl-internship-data', 'Dati pratica', [
            ['practice_number'],
            ['reporting_id', 'decree_id'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Numero pratica e riferimenti di rendicontazione.']],
    ];
}
