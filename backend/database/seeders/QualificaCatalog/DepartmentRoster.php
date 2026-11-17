<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;

/**
 * The people of the department questionnaires (spec 0408), by account email:
 * the department roles they hold, the targeted grants they hold on top
 * (direct permissions, keys of DepartmentPermissionGrants) and the replaced
 * roles they keep. Whoever is not listed is left alone; the list grows as the
 * other departments answer.
 */
final class DepartmentRoster
{
    /**
     * ISO/GDPR: "UFFICIO PINIFICAZIONE E RESP SI" (D-6).
     *
     * @var array<int, string>
     */
    private const array ISO_PLANNING_GRANTS = [
        'registries_edit', 'registries_delete', 'registries_transfer', 'registries_history',
        'opportunities_view', 'opportunities_view_competence', 'opportunities_edit', 'opportunities_export',
        'opportunities_documents', 'opportunities_history',
        'quotes_view', 'quotes_view_competence', 'quotes_edit', 'quotes_delete', 'quotes_transfer', 'quotes_history',
        'work_orders_view_competence', 'work_orders_edit', 'work_orders_economics', 'work_orders_manage_costs',
        'work_orders_invoices', 'work_orders_payments', 'work_orders_delete', 'work_orders_transfer', 'work_orders_history',
        'contracts_view', 'contracts_view_competence', 'contracts_validate', 'contracts_program', 'contracts_change_status',
        'contracts_terminate', 'contracts_reactivate', 'contracts_export', 'contracts_history',
        'tasks_transfer',
        'proforma_view', 'proforma_request', 'invoices_view', 'invoices_export',
        'purchase_invoices_view', 'commission_accruals_view',
    ];

    /**
     * Sicurezza: "solo responsabile" / "SOLO FERNANDO SI".
     *
     * @var array<int, string>
     */
    private const array SAFETY_HEAD_GRANTS = [
        'work_orders_transfer', 'commission_accruals_view', 'commission_settlements_view', 'commissions_export',
    ];

    /**
     * @var array<string, array{roles: array<int, string>, grants?: array<int, string>, keep?: array<int, string>}>
     */
    public const array USERS = [
        // ISO/GDPR
        'francesco.ienco@qualificagroup.com' => ['roles' => [Department::ISO]],
        'noemi.nigliato@qualificagroup.it' => ['roles' => [Department::ISO]],
        'maria.savarese@qualificagroup.com' => ['roles' => [Department::ISO]],
        'paola.sena@qualificagroup.com' => ['roles' => [Department::ISO]],
        'ivana.mango@qualificagroup.com' => ['roles' => [Department::ISO]],
        'antoniosalvatore.peluso@qualificagroup.com' => ['roles' => [Department::ISO]],
        'antonio.demartino@qualificagroup.com' => ['roles' => [Department::ISO]],
        'francesca.russodelprete@qualificagroup.com' => ['roles' => [Department::ISO.Department::SENIOR_SUFFIX], 'grants' => self::ISO_PLANNING_GRANTS, 'keep' => ['admin']],
        'mario.esposito@qualificagroup.com' => ['roles' => [Department::ISO], 'keep' => ['admin']],
        'annarita.palumbo@qualificagroup.com' => ['roles' => [Department::ISO], 'grants' => self::ISO_PLANNING_GRANTS],
        'consiglia.cosentino@qualificagroup.com' => ['roles' => [Department::ISO], 'grants' => self::ISO_PLANNING_GRANTS],

        // Sicurezza
        'fernando.annunziata@qualificagroup.com' => ['roles' => [Department::SAFETY.Department::SENIOR_SUFFIX], 'grants' => self::SAFETY_HEAD_GRANTS],
        'pasquale.orsusanno@qualificagroup.com' => ['roles' => [Department::SAFETY.Department::SENIOR_SUFFIX]],
        'francesco.dimaio@qualificagroup.com' => ['roles' => [Department::SAFETY]],
        'elio.rettori@qualificagroup.com' => ['roles' => [Department::SAFETY]],
        'gaetano.farina@qualificagroup.com' => ['roles' => [Department::SAFETY]],

        // Travel
        'maria.pezzella@qualificagroup.com' => ['roles' => [Department::TRAVEL]],

        // Accreditamenti e Gestione Sedi (Agus also in Finanza Agevolata, "SOLO COMMESSE")
        'luigifabio.pescosolido@qualificagroup.com' => ['roles' => [Department::ACCREDITATION]],
        'giuseppe.agus@qualificagroup.com' => ['roles' => [Department::ACCREDITATION, Department::SUBSIDISED_FINANCE]],

        // Finanza Agevolata, Istruzione e Progettazione
        'ferdinando.flagiello@qualificagroup.com' => ['roles' => [Department::SUBSIDISED_FINANCE.Department::SENIOR_SUFFIX]],
        'federica.degennaro@qualificagroup.com' => ['roles' => [Department::SUBSIDISED_FINANCE.Department::SENIOR_SUFFIX]],
        'mariarita.balzamo@qualificagroup.com' => ['roles' => [Department::SUBSIDISED_FINANCE]],

        // HR
        'anna.castaldo@qualificagroup.com' => ['roles' => [Department::HUMAN_RESOURCES.Department::SENIOR_SUFFIX]],
        'valentina.sorrentino@qualificagroup.com' => ['roles' => [Department::HUMAN_RESOURCES]],
        'amelia.iorio@qualificagroup.com' => ['roles' => [Department::HUMAN_RESOURCES]],

        // Gare e Avvalimenti
        'pina.derosa@qualificagroup.com' => ['roles' => [Department::TENDERS]],

        // Marketing
        'sabino.figurelli@qualificagroup.com' => ['roles' => [Department::MARKETING]],
        'miriam.dellaversana@qualificagroup.com' => ['roles' => [Department::MARKETING]],
        'felice.salomone@qualificagroup.com' => ['roles' => [Department::MARKETING]],
        'claudia.testa@qualificagroup.com' => ['roles' => [Department::MARKETING]],

        // SOA
        'roberto.sepe@qualificagroup.com' => ['roles' => [Department::SOA]],
        'francesca.mauriello@qualificagroup.com' => ['roles' => [Department::SOA]],
    ];
}
