<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The COMMESSA set of the "ISO" branch (user directive 2026-10-21), kept apart
 * from IsoCatalogue for size: the sections, fields and order of the [OLD] ISO
 * Commessa (user directive 2026-11-27) — "Dettagli Lavorazioni", "Focus
 * Lavorazione Iso", "Verifiche Ispettive" and "Dettagli Lavorazioni Iso" —
 * assigned on the root and inherited by its subcategories. Read by
 * QualificaBranchWorkOrderSeeder and QualificaIsoWorkDetailsSeeder.
 *
 * The legacy shows them all, always: no section is conditional here. NOT a
 * field, by design: the "Stato Lavorazione" — an ISO deal uses the default
 * working states, like SOA (user decision 2026-10-21).
 */
final class IsoWorkOrderCatalogue
{
    private const array RADIO = ['display' => 'radio'];

    private const array LOCATION_OPTIONS = [
        ['value' => 'on_site', 'label' => 'On site'],
        ['value' => 'off_site', 'label' => 'Off site'],
    ];

    private const array CERTIFICATES_CONFIG = [
        'columns' => [
            ['key' => 'standard', 'label' => 'Standard', 'type' => 'text'],
            ['key' => 'issue_date', 'label' => 'Data emissione certificato', 'type' => 'date'],
            ['key' => 'expiry_date', 'label' => 'Data scadenza certificato', 'type' => 'date'],
        ],
        'summary' => ['column' => 'expiry_date', 'strategy' => 'max'],
    ];

    /** The legacy marks these four required when an inspection is added. */
    private const array INSPECTIONS_CONFIG = [
        'columns' => [
            ['key' => 'date', 'label' => 'Data V.I.', 'type' => 'date', 'required' => true],
            ['key' => 'inspector', 'label' => 'Ispettore', 'type' => 'text', 'required' => true],
            ['key' => 'support', 'label' => 'Supporto verifica', 'type' => 'enum', 'required' => true, 'options' => self::LOCATION_OPTIONS],
            ['key' => 'stage', 'label' => 'Fase', 'type' => 'enum', 'required' => true, 'options' => [
                ['value' => 'stage_1', 'label' => 'Stage 1'],
                ['value' => 'stage_2', 'label' => 'Stage 2'],
            ]],
            ['key' => 'alert', 'label' => 'Avviso', 'type' => 'boolean'],
        ],
        'selectable' => ['key' => 'active', 'label' => 'Attivo'],
        'summary' => ['column' => 'date', 'strategy' => 'selected', 'grid_label' => 'Data verifica ispettiva'],
    ];

    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, config?: array<string, mixed>}>
     */
    public const array ATTRIBUTES = [
        ['code' => 'iso_certificates', 'name' => 'Standard e certificati', 'type' => 'table', 'config' => self::CERTIFICATES_CONFIG],
        ['code' => 'iso_last_audit_date', 'name' => 'Data ultimo audit', 'type' => 'date'],
        ['code' => 'iso_certificate_year', 'name' => 'Annualità del certificato', 'type' => 'enum', 'options' => [
            ['value' => 'first_issue', 'label' => 'Prima emissione'],
            ['value' => 'surveillance_1', 'label' => 'I sorveglianza'],
            ['value' => 'surveillance_2', 'label' => 'II sorveglianza'],
            ['value' => 'surveillance_3', 'label' => 'III sorveglianza'],
            ['value' => 'surveillance_4', 'label' => 'IV sorveglianza'],
            ['value' => 'surveillance_5', 'label' => 'V sorveglianza'],
            ['value' => 'recertification', 'label' => 'Ricertificazione'],
        ]],
        ['code' => 'iso_scope', 'name' => 'Scopo proposto', 'type' => 'textarea'],
        ['code' => 'iso_urgent_issue_date', 'name' => 'Data urgenza emissione', 'type' => 'date'],
        ['code' => 'iso_issue_urgency', 'name' => 'Urgenza emissione', 'type' => 'text'],
        ['code' => 'iso_visit_scope', 'name' => 'Scopo', 'type' => 'textarea'],
        ['code' => 'iso_interview_location', 'name' => 'Intervista in sede', 'type' => 'enum', 'config' => self::RADIO, 'options' => self::LOCATION_OPTIONS],
        ['code' => 'iso_audit_support_location', 'name' => 'Supporto verifica operatore', 'type' => 'enum', 'config' => self::RADIO, 'options' => self::LOCATION_OPTIONS],
        ['code' => 'iso_work_hours', 'name' => 'Ore di lavorazione', 'type' => 'decimal'],
        ['code' => 'iso_assignment_date', 'name' => 'Data di assegnazione', 'type' => 'date'],
        ['code' => 'iso_work_end_date', 'name' => 'Fine lavorazione', 'type' => 'date'],
        ['code' => 'iso_reference_body', 'name' => 'Ente di riferimento', 'type' => 'text'],
        ['code' => 'iso_interview_hours', 'name' => 'Ore intervista', 'type' => 'decimal'],
        ['code' => 'iso_audit_support_hours', 'name' => 'Ore supporto verifica', 'type' => 'decimal'],
        ['code' => 'iso_quotation', 'name' => 'Quotazione', 'type' => 'decimal'],
        ['code' => 'iso_scope_confirmed', 'name' => 'Scopo confermato', 'type' => 'enum', 'config' => self::RADIO, 'options' => [
            ['value' => 'confirmed', 'label' => 'Confermato'],
            ['value' => 'changed', 'label' => 'Modificato'],
        ]],
        ['code' => 'iso_outgoing_scope', 'name' => 'Scopo in uscita', 'type' => 'textarea'],
        ['code' => 'iso_consultant_notes', 'name' => 'Note consulente', 'type' => 'textarea'],
        ['code' => 'iso_inspections', 'name' => 'Verifiche ispettive', 'type' => 'table', 'config' => self::INSPECTIONS_CONFIG],
        ['code' => 'iso_official_audit_date', 'name' => 'Data verifica effettiva', 'type' => 'date'],
    ];

    /**
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['iso-work-details', 'Dettagli lavorazioni', [
            ['iso_certificates'],
            ['iso_last_audit_date', 'iso_certificate_year'],
            ['iso_issue_urgency', 'iso_urgent_issue_date'],
            ['iso_visit_scope'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Certificati, audit, urgenza e scopo della certificazione.']],
        ['iso-work-focus', 'Focus lavorazione ISO', [
            ['iso_interview_location', 'iso_audit_support_location'],
            ['iso_work_hours', 'iso_assignment_date'],
            ['iso_work_end_date', 'iso_reference_body'],
            ['iso_interview_hours', 'iso_audit_support_hours'],
            ['iso_quotation'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Modalità, ore, date e quotazione della lavorazione.']],
        ['iso-inspections', 'Verifiche ispettive', [
            ['iso_inspections'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Verifiche dell\'ente: segna come attiva quella in corso.']],
        ['iso-work-scope', 'Dettagli lavorazioni ISO', [
            ['iso_scope'],
            ['iso_scope_confirmed'],
            ['iso_outgoing_scope'],
            ['iso_consultant_notes'],
            ['iso_official_audit_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Scopo proposto, confermato e in uscita, note e verifica effettiva.']],
    ];
}
