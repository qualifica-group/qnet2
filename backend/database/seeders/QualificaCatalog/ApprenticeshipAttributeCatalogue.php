<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The OFFERTA-context fields of the apprenticeship practices, transcribed from
 * the "Apprendistato - Campi Operatore" sheet (user directive 2026-10-05): its
 * DATI PRATICA, FORMAZIONE and FORMAZIONE - UNITÀ FORMATIVE blocks. Pure data,
 * like AplInternshipAttributeCatalogue — QualificaCatalogSeeder creates and
 * assigns them on the CATEGORY, QualificaQuoteLayoutSeeder lays them out in
 * the SECTIONS below.
 *
 * They are the category's WHOLE offer form: it is cut off the "APL" root
 * (CategoryInheritanceRules), which on an imported database carries the
 * Ricerca & Selezione job-description fields.
 *
 * NOT FIELDS, by design, as for the internships: "Operatore" is the offer's
 * `operator_id`, "Commerciale" and "Segnalatore" its `commercial_id` and
 * `reporter_id`; "Stato pratica" is the working state and "Percorso" its
 * history (ApprenticeshipWorkflowStatusCatalogue). The annualità is the
 * product ("Formazione Apprendistato 1°/2°/3° Anno"). Out of scope (user
 * directive 2026-10-05): DATI AZIENDA, DATI APPRENDISTA, DOCUMENTAZIONE.
 *
 * Every training unit (UF) is the sheet's "Casella + motivo + giorni" triple:
 * whether Qualifica delivered it, why not when it did not, and the training
 * days with the hours done on each. Its planned hours are the section
 * description. The per-UF attachments have no field type to land on.
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class ApprenticeshipAttributeCatalogue
{
    /**
     * Seeded under "APL" so it exists on a clean database too; on an imported
     * one ProductCategoriesSource adopts it by this exact name.
     */
    public const string CATEGORY = 'Formazione Apprendistato';

    /**
     * The sheet's suggestions for a UF not delivered by Qualifica.
     *
     * @var list<array{value: string, label: string}>
     */
    private const array REASON_OPTIONS = [
        ['value' => 'internal_company_training', 'label' => 'Formazione interna aziendale'],
        ['value' => 'other_accredited_body', 'label' => 'Svolta da altro ente accreditato'],
        ['value' => 'not_yet_done', 'label' => 'Non ancora svolta'],
    ];

    /**
     * One row per training day: the date and the hours done that day.
     *
     * @var array{columns: list<array<string, mixed>>}
     */
    private const array SESSIONS_CONFIG = ['columns' => [
        ['key' => 'day', 'label' => 'Giorno', 'type' => 'date', 'required' => true],
        ['key' => 'hours', 'label' => 'Ore svolte', 'type' => 'decimal', 'required' => true, 'config' => ['min' => 0, 'decimals' => 1]],
    ]];

    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>, config?: array<string, mixed>}>
     */
    public const array ATTRIBUTES = [
        AplInternshipAttributeCatalogue::DECREE_STATUS,
        AplInternshipAttributeCatalogue::DECREE_ID,
        ['code' => 'teaching_tutor', 'name' => 'Tutor didattico', 'type' => 'relation', 'relation_target' => [
            'entity_type' => 'users',
            'cardinality' => 'one',
            'for_select_resource' => 'users',
        ]],
        ['code' => 'hiring_date', 'name' => 'Data assunzione', 'type' => 'date'],
        ['code' => 'contract_duration_months', 'name' => 'Durata (mesi)', 'type' => 'integer'],
        ['code' => 'contract_end_date', 'name' => 'Data fine contratto', 'type' => 'date'],
        AplInternshipAttributeCatalogue::REPORTING_ID,
        ['code' => 'company_training_capacity', 'name' => 'Capacità formativa dell\'azienda', 'type' => 'enum', 'options' => [
            ['value' => 'internal_delivered_internally', 'label' => 'Ha capacità formativa interna e svolge internamente la formazione'],
            ['value' => 'internal_accredited_body', 'label' => 'Ha capacità formativa interna ma individua un ente accreditato'],
            ['value' => 'accredited_body', 'label' => 'Non ha capacità formativa interna e individua un ente accreditato'],
        ]],
        ['code' => 'apprenticeship_y1_uf1_done_by_us', 'name' => 'Fatta da noi (A1 UF1)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y1_uf1_reason', 'name' => 'Motivo (A1 UF1)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y1_uf1_sessions', 'name' => 'Giorni di formazione (A1 UF1)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y1_uf2_done_by_us', 'name' => 'Fatta da noi (A1 UF2)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y1_uf2_reason', 'name' => 'Motivo (A1 UF2)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y1_uf2_sessions', 'name' => 'Giorni di formazione (A1 UF2)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y1_uf3_done_by_us', 'name' => 'Fatta da noi (A1 UF3)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y1_uf3_reason', 'name' => 'Motivo (A1 UF3)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y1_uf3_sessions', 'name' => 'Giorni di formazione (A1 UF3)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y1_stage_done_by_us', 'name' => 'Fatta da noi (A1 Stage)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y1_stage_reason', 'name' => 'Motivo (A1 Stage)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y1_stage_sessions', 'name' => 'Giorni di formazione (A1 Stage)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y2_uf1_done_by_us', 'name' => 'Fatta da noi (A2 UF1)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y2_uf1_reason', 'name' => 'Motivo (A2 UF1)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y2_uf1_sessions', 'name' => 'Giorni di formazione (A2 UF1)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y2_uf4_done_by_us', 'name' => 'Fatta da noi (A2 UF4)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y2_uf4_reason', 'name' => 'Motivo (A2 UF4)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y2_uf4_sessions', 'name' => 'Giorni di formazione (A2 UF4)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y2_uf5_done_by_us', 'name' => 'Fatta da noi (A2 UF5)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y2_uf5_reason', 'name' => 'Motivo (A2 UF5)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y2_uf5_sessions', 'name' => 'Giorni di formazione (A2 UF5)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y2_stage_done_by_us', 'name' => 'Fatta da noi (A2 Stage)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y2_stage_reason', 'name' => 'Motivo (A2 Stage)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y2_stage_sessions', 'name' => 'Giorni di formazione (A2 Stage)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y3_uf2_done_by_us', 'name' => 'Fatta da noi (A3 UF2)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y3_uf2_reason', 'name' => 'Motivo (A3 UF2)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y3_uf2_sessions', 'name' => 'Giorni di formazione (A3 UF2)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y3_uf5_done_by_us', 'name' => 'Fatta da noi (A3 UF5)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y3_uf5_reason', 'name' => 'Motivo (A3 UF5)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y3_uf5_sessions', 'name' => 'Giorni di formazione (A3 UF5)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
        ['code' => 'apprenticeship_y3_stage_done_by_us', 'name' => 'Fatta da noi (A3 Stage)', 'type' => 'boolean'],
        ['code' => 'apprenticeship_y3_stage_reason', 'name' => 'Motivo (A3 Stage)', 'type' => 'enum', 'options' => self::REASON_OPTIONS],
        ['code' => 'apprenticeship_y3_stage_sessions', 'name' => 'Giorni di formazione (A3 Stage)', 'type' => 'table', 'config' => self::SESSIONS_CONFIG],
    ];

    /**
     * The offer form in the sheet's reading order: the practice data, then
     * the company's training capacity, then one section per UF grouped by
     * annualità. Every section white (user directive 2026-10-05).
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['apprenticeship-data', 'Dati pratica', [
            ['decree_status', 'decree_id'],
            ['teaching_tutor'],
            ['hiring_date', 'contract_duration_months'],
            ['contract_end_date', 'reporting_id'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Decreto, tutor didattico e date del contratto di apprendistato.']],
        ['apprenticeship-training', 'Formazione', [
            ['company_training_capacity'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 1, 'description' => 'Per ogni unità formativa: se non è fatta da noi indica il motivo, altrimenti registra i giorni e le ore svolte.']],
        ['apprenticeship-y1-uf1', 'Annualità 1 - UF1 Accoglienza', [
            ['apprenticeship_y1_uf1_done_by_us', 'apprenticeship_y1_uf1_reason'],
            ['apprenticeship_y1_uf1_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 2 (Sicurezza sui luoghi di lavoro).']],
        ['apprenticeship-y1-uf2', 'Annualità 1 - UF2 Formazione generale sulla sicurezza', [
            ['apprenticeship_y1_uf2_done_by_us', 'apprenticeship_y1_uf2_reason'],
            ['apprenticeship_y1_uf2_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 10 (Sicurezza sui luoghi di lavoro).']],
        ['apprenticeship-y1-uf3', 'Annualità 1 - UF3 La contrattualistica del lavoro: diritti e doveri dei lavoratori, la contrattazione collettiva', [
            ['apprenticeship_y1_uf3_done_by_us', 'apprenticeship_y1_uf3_reason'],
            ['apprenticeship_y1_uf3_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 2 (Sicurezza sui luoghi di lavoro).']],
        ['apprenticeship-y1-stage', 'Annualità 1 - UF5 Stage', [
            ['apprenticeship_y1_stage_done_by_us', 'apprenticeship_y1_stage_reason'],
            ['apprenticeship_y1_stage_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 26 (Stage).']],
        ['apprenticeship-y2-uf1', 'Annualità 2 - UF1 Comportamenti sicuri sul luogo di lavoro', [
            ['apprenticeship_y2_uf1_done_by_us', 'apprenticeship_y2_uf1_reason'],
            ['apprenticeship_y2_uf1_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 12 (Sistemi di qualità aziendale).']],
        ['apprenticeship-y2-uf4', 'Annualità 2 - UF4 Informatica avanzata: internet e posta elettronica', [
            ['apprenticeship_y2_uf4_done_by_us', 'apprenticeship_y2_uf4_reason'],
            ['apprenticeship_y2_uf4_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 2 (Sistemi di qualità aziendale).']],
        ['apprenticeship-y2-uf5', 'Annualità 2 - UF5 Tecniche di comunicazione e relazione interpersonale', [
            ['apprenticeship_y2_uf5_done_by_us', 'apprenticeship_y2_uf5_reason'],
            ['apprenticeship_y2_uf5_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 4 (Sistemi di qualità aziendale).']],
        ['apprenticeship-y2-stage', 'Annualità 2 - UF5 Stage', [
            ['apprenticeship_y2_stage_done_by_us', 'apprenticeship_y2_stage_reason'],
            ['apprenticeship_y2_stage_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 22 (Stage).']],
        ['apprenticeship-y3-uf2', 'Annualità 3 - UF2 Assumere comportamenti civici e sociali', [
            ['apprenticeship_y3_uf2_done_by_us', 'apprenticeship_y3_uf2_reason'],
            ['apprenticeship_y3_uf2_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 4 (Organizzazione e comunicazione aziendale).']],
        ['apprenticeship-y3-uf5', 'Annualità 3 - UF5 Elementi di base della professione/mestiere', [
            ['apprenticeship_y3_uf5_done_by_us', 'apprenticeship_y3_uf5_reason'],
            ['apprenticeship_y3_uf5_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 4 (Organizzazione e comunicazione aziendale).']],
        ['apprenticeship-y3-stage', 'Annualità 3 - UF5 Stage', [
            ['apprenticeship_y3_stage_done_by_us', 'apprenticeship_y3_stage_reason'],
            ['apprenticeship_y3_stage_sessions'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Ore previste: 32 (Stage).']],
    ];
}
