<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The COMMESSA set of the "GDPR" branch (user directive 2026-11-27): the
 * Commessa fields and form of "[OLD] GDPR" ("[OLD] DPO", "[OLD]
 * GDPR_Lavorazione"), recreated on the current root and inherited by its two
 * subcategories. Read by QualificaGdprWorkOrderFieldsSeeder.
 *
 * NOT a field, by design: the legacy "Stato Lavorazione" (`stato_consulenza_9`)
 * — a GDPR deal uses the default working states, as ISO and SOA do (user
 * decision 2026-10-21 for those branches).
 */
final class GdprWorkOrderCatalogue
{
    private const array RADIO = ['display' => 'radio'];

    private const array LOCATION_OPTIONS = [
        ['value' => 'on_site', 'label' => 'On site'],
        ['value' => 'off_site', 'label' => 'Off site'],
    ];

    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, config?: array<string, mixed>}>
     */
    public const array ATTRIBUTES = [
        ['code' => 'gdpr_onsite_visits', 'name' => 'Numero accessi on site', 'type' => 'integer'],
        ['code' => 'gdpr_offsite_visits', 'name' => 'Numero accessi off site', 'type' => 'integer'],
        ['code' => 'gdpr_interview_location', 'name' => 'Intervista', 'type' => 'enum', 'config' => self::RADIO, 'options' => self::LOCATION_OPTIONS],
        ['code' => 'gdpr_training_location', 'name' => 'Formazione', 'type' => 'enum', 'config' => self::RADIO, 'options' => self::LOCATION_OPTIONS],
        ['code' => 'gdpr_work_hours', 'name' => 'Numero ore di lavoro', 'type' => 'decimal'],
        ['code' => 'gdpr_work_start_date', 'name' => 'Data inizio lavorazione', 'type' => 'date'],
        ['code' => 'gdpr_work_end_date', 'name' => 'Data fine lavorazione', 'type' => 'date'],
        ['code' => 'gdpr_last_call_date', 'name' => 'Data ultima chiamata', 'type' => 'date'],
        ['code' => 'gdpr_callback_date', 'name' => 'Data richiamo', 'type' => 'date'],
        ['code' => 'gdpr_consultant_notes', 'name' => 'Note consulente', 'type' => 'textarea'],
        ['code' => 'gdpr_next_consulting_date', 'name' => 'Prossima consulenza', 'type' => 'date'],
    ];

    /**
     * The legacy sections, in the legacy order.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['gdpr-work-contract', 'Contratto - Accessi', [
            ['gdpr_onsite_visits', 'gdpr_offsite_visits'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Accessi previsti dal contratto.']],
        ['gdpr-work-planning', 'Pianificazione annuale', [
            ['gdpr_interview_location', 'gdpr_training_location'],
            ['gdpr_work_hours'],
            ['gdpr_work_start_date', 'gdpr_work_end_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Modalità, ore e date della lavorazione.']],
        ['gdpr-work-progress', 'Lavorazione annuale', [
            ['gdpr_last_call_date', 'gdpr_callback_date'],
            ['gdpr_consultant_notes'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Contatti con il cliente e note del consulente.']],
        ['gdpr-work-previous-planning', 'Pianificazione da commesse precedenti', [
            ['gdpr_next_consulting_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Prossima consulenza prevista.']],
    ];

    /**
     * Legacy code => current code (spec 0429): only the types that share a
     * grid column. Not linked: the two enums (legacy values "1"/"0" against
     * on_site/off_site) and `note_consulente`, which [OLD] ISO shares and is
     * already linked to `iso_consultant_notes` (a field has one current field).
     */
    public const array LINKS = [
        'gdpr_accessi_onsite' => 'gdpr_onsite_visits',
        'gdpr_accessi_offsite' => 'gdpr_offsite_visits',
        'gdp_ore_lavorazione' => 'gdpr_work_hours',
        'gdpr_inizio_lavorazione' => 'gdpr_work_start_date',
        'gdpr_fine_lavorazione' => 'gdpr_work_end_date',
        'gdpr_ultima_chiamata' => 'gdpr_last_call_date',
        'gdpr_richiamo' => 'gdpr_callback_date',
        'gdpr_prossima_consulenza' => 'gdpr_next_consulting_date',
    ];
}
