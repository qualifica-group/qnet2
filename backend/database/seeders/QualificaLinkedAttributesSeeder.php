<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\LinksLegacyAttributes;
use Illuminate\Database\Seeder;

/**
 * One-shot mapping (user directive 2026-11-24, spec 0429 D-6): each legacy
 * [OLD] ISO field points to the current ISO field it continues, so the grids
 * show them in one column. Only types that share a column are mapped (D-2);
 * enum, table and number/text pairs stay two columns, as before.
 *
 * Idempotent: an existing link is never overwritten, a pair whose field is
 * missing or would break the rules is skipped and reported.
 * Run with `php artisan db:seed --class=QualificaLinkedAttributesSeeder`.
 */
class QualificaLinkedAttributesSeeder extends Seeder
{
    use LinksLegacyAttributes;

    /** Legacy code => current code. Commessa fields first, then offer fields. */
    public const array LINKS = [
        'data_inizio_lavorazione' => 'iso_assignment_date',
        'data_fine_lavorazione' => 'iso_work_end_date',
        'data_ultimo_audit' => 'iso_last_audit_date',
        'data_urgenza_emissione' => 'iso_urgent_issue_date',
        'data_verifica_ufficiale' => 'iso_official_audit_date',
        'urgenza_emissione' => 'iso_issue_urgency',
        'ente_riferimento' => 'iso_reference_body',
        'quotazione' => 'iso_quotation',
        'ore_lavorazione' => 'iso_work_hours',
        'scopo' => 'iso_scope',
        'scopo_annuale' => 'iso_outgoing_scope',
        'note_consulente' => 'iso_consultant_notes',

        'application_filed' => 'iso_application_scope',
        'application_field_note' => 'iso_application_scope_notes',
        'oldente' => 'iso_previous_body',
        'newente' => 'iso_requested_body',
        'data_endcertificate' => 'iso_certificate_expiry_date',
        'data_firstaudit' => 'iso_first_audit_date',
        'data_requestemission' => 'iso_requested_issue_date',
        'note_emission' => 'iso_issue_reason_notes',
        'grp1_employees_tot' => 'iso_g1_total_employees',
        'grp1_revenue' => 'iso_g1_revenue',
        'grp1_note' => 'iso_g1_notes',
        'grp2_surface_mq' => 'iso_g2_surface_sqm',
        'grp2_number_production' => 'iso_g2_production_lines',
        'grp2_number_process' => 'iso_g2_processes',
        'grp2_number_floors_haccp' => 'iso_g2_haccp_plans',
        'grp2_activity_description' => 'iso_g2_activity_description',
        'grp3_employees_tot' => 'iso_g3_total_employees',
        'grp3_energy_consumption' => 'iso_g3_energy_employees',
        'grp3_number_energy_source' => 'iso_g3_energy_sources',
        'grp4_employees_hight_risk' => 'iso_g4_high_risk_employees',
        'grp4_employees_medium_risk' => 'iso_g4_medium_risk_employees',
        'grp4_employees_low_risk' => 'iso_g4_low_risk_employees',
        'grp5_number_drivers' => 'iso_g5_drivers',
        'grp5_number_employees' => 'iso_g5_process_employees',
        'grp6_executive_manager' => 'iso_g6_managers',
        'grp6_clerks_workers' => 'iso_g6_clerks_workers',
        'grp6_num_male_famale' => 'iso_g6_male_female_ratio',
        'num_gu_advice_init' => 'iso_initial_consulting_days',
        'num_gu_advice_survillance' => 'iso_surveillance_consulting_days',
    ];

    public function run(): void
    {
        $this->command?->info('Campi collegati: '.$this->linkLegacyAttributes(self::LINKS));
    }
}
