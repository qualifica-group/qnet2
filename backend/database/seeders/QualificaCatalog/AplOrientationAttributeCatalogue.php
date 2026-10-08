<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The OFFERTA-context fields of the APL orientation practices (Orientamento /
 * SFL GOL), transcribed from the "Campi Misure APL" sheet (user directive
 * 2026-10-05, which brings back the 2026-10-01 sheet the internships had
 * replaced). Pure data, like AplInternshipAttributeCatalogue —
 * QualificaCatalogSeeder creates and assigns them on the CATEGORY,
 * QualificaQuoteLayoutSeeder lays them out in the SECTIONS below.
 *
 * They are the category's WHOLE offer form: it is cut off the "APL" root
 * (CategoryInheritanceRules), so a field assigned on the root never reaches it.
 *
 * "Anagrafica Utente" is a field of its own, a link to the registry of the
 * person the practice is for (user directive 2026-10-06): the opportunity's
 * registry is the customer, not necessarily that person.
 *
 * NOT FIELDS, by design, as for the other APL practices: "Operatore",
 * "Commerciale" and "Segnalatore" are the offer's `operator_id`,
 * `commercial_id` and `reporter_id`; "Stato
 * pratica" is the working state (AplOrientationWorkflowStatusCatalogue);
 * "SGA", "Commessa" and "Valore commessa" belong to the linked work order.
 * The header's "Ultima politica" and the practice data's "Ultima politica
 * attiva" are one date, as are the two "Politiche erogabili". The sheet's
 * automations (end date from the deliverable policies, SFL renewal state
 * from the last policy's month) are proposals, not seeded behaviour: both
 * values are entered by hand.
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class AplOrientationAttributeCatalogue
{
    /**
     * The APL root's single-offer category (CatalogProducts), already seeded
     * and selectable: this catalogue only gives it a form of its own. It
     * replaces the legacy "Orientamento Specialistico", imported under "APL
     * old" (LegacyAplBranch, user directive 2026-10-05).
     */
    public const string CATEGORY = 'Orientamento specialistico';

    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>, config?: array<string, mixed>}>
     */
    public const array ATTRIBUTES = [
        ['code' => 'user_registry', 'name' => 'Anagrafica Utente', 'type' => 'relation', 'relation_target' => [
            'entity_type' => 'registries',
            'cardinality' => 'one',
            'for_select_resource' => 'registries',
        ]],
        ['code' => 'sfl_renewal_status', 'name' => 'SFL', 'type' => 'enum', 'options' => [
            ['value' => 'to_renew', 'label' => 'Da rinnovare'],
            ['value' => 'renewed', 'label' => 'Rinnovato'],
        ]],
        AplInternshipAttributeCatalogue::DECREE_STATUS,
        ['code' => 'deliverable_policies', 'name' => 'Politiche erogabili', 'type' => 'integer', 'config' => ['min' => 1, 'max' => 4]],
        ['code' => 'last_active_policy_date', 'name' => 'Ultima politica attiva', 'type' => 'date'],
        ['code' => 'orientation_measure', 'name' => 'Misura', 'type' => 'enum', 'options' => [
            ['value' => 'intake', 'label' => 'Presa in carico'],
            ['value' => 'orientation', 'label' => 'Orientamento'],
            ['value' => 'job_support', 'label' => 'Accompagnamento'],
        ]],
        ['code' => 'sfl_months_received', 'name' => 'Mensilità SFL già percepite', 'type' => 'integer', 'config' => ['min' => 0, 'max' => 12]],
        AplInternshipAttributeCatalogue::PRACTICE_END_DATE,
        AplInternshipAttributeCatalogue::REPORTING_ID,
        AplInternshipAttributeCatalogue::DECREE_ID,
        ['code' => 'orientation_convocation_date', 'name' => 'Convocazione', 'type' => 'date'],
        ['code' => 'orientation_intake_date', 'name' => 'Presa in carico', 'type' => 'date'],
        ['code' => 'orientation_session_date', 'name' => 'Orientamento', 'type' => 'date'],
        ['code' => 'orientation_job_support_1_date', 'name' => 'Accompagnamento 1', 'type' => 'date'],
        ['code' => 'orientation_job_support_2_date', 'name' => 'Accompagnamento 2', 'type' => 'date'],
        ['code' => 'orientation_job_support_3_date', 'name' => 'Accompagnamento 3', 'type' => 'date'],
    ];

    /**
     * The offer form in the sheet's reading order: the header the operator
     * checks first, then the practice data and the path dates. Every section
     * white (user directive 2026-10-05).
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['apl-orientation-header', 'Testata', [
            ['user_registry'],
            ['sfl_renewal_status', 'decree_status'],
            ['deliverable_policies', 'last_active_policy_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Anagrafica dell\'utente, rinnovo SFL, decreto e politiche attive della pratica.']],
        self::DATA_SECTION,
        self::PATH_SECTION,
    ];

    /**
     * The form the 2026-10-05 revision seeded, before "Anagrafica Utente" led
     * the header: QualificaQuoteLayoutSeeder recognises it byte for byte and
     * recomposes it, or an installation already seeded would get the new field
     * only in the synthesized "other information" section.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array PREVIOUS_SECTIONS = [
        ['apl-orientation-header', 'Testata', [
            ['sfl_renewal_status', 'decree_status'],
            ['deliverable_policies', 'last_active_policy_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Rinnovo SFL, decreto e politiche attive della pratica.']],
        self::DATA_SECTION,
        self::PATH_SECTION,
    ];

    /**
     * @var array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}
     */
    private const array DATA_SECTION = ['apl-orientation-data', 'Dati pratica', [
        ['orientation_measure', 'sfl_months_received'],
        ['practice_end_date'],
        ['reporting_id', 'decree_id'],
    ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Misura, mensilità SFL già percepite (su 12) e riferimenti di rendicontazione.']];

    /**
     * @var array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}
     */
    private const array PATH_SECTION = ['apl-orientation-path', 'Percorso', [
        ['orientation_convocation_date', 'orientation_intake_date'],
        ['orientation_session_date', 'orientation_job_support_1_date'],
        ['orientation_job_support_2_date', 'orientation_job_support_3_date'],
    ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Date delle tappe del percorso di orientamento e accompagnamento.']];
}
