<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The senior sales role (spec 0344, user directive 2026-11-02). Pure data,
 * read by QualificaRoleSeeder (the matrix) and QualificaSeniorSalesSeeder
 * (who holds the role). Like CommercialBackOfficeRoleCatalogue, a module list
 * held on top of the person's mansione.
 */
final class SeniorSalesRoleCatalogue
{
    public const string ROLE = 'commerciale-senior';

    public const string DESCRIPTION = 'Commerciale Senior';

    /**
     * Granted in full, every ability included, `viewAll` too (D-2, D-3).
     *
     * @var array<int, string>
     */
    public const array MODULES = [
        'registries',
        'referents',
        'opportunities',
        'quotes',
        'work-orders',
    ];

    /**
     * Read by the forms of MODULES outside the for-select pickers (D-4), as
     * CommercialBackOfficeRoleCatalogue::SELECT_ONLY_RESOURCES.
     *
     * @var array<int, string>
     */
    public const array SELECT_ONLY_RESOURCES = [
        'product-categories',
        'operational-sites',
    ];

    /**
     * Listed by name (D-1). Antimo Dalvini holds only a deactivated account
     * today: it receives the role all the same (D-6).
     *
     * @var array<int, string>
     */
    public const array USERS = [
        'marco.baldi@qualificagroup.com',
        'marco.fedele@qualificagroup.com',
        'anna.garofalo@qualificagroup.com',
        'antonio.alvoni@qualificagroup.com',
        'constantin.popa@qualificagroup.com',
        'vincenzo.crisci@qualificagroup.com',
        'biagio.fusco@qualificagroup.it',
        'antimo.dalvini@qualificagroup.com',
    ];
}
