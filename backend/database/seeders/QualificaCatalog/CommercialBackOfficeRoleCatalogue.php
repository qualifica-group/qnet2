<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The commercial back office's application role (spec 0299, user directive
 * 2026-10-22). Pure data, read by QualificaRoleSeeder (the matrix) and
 * QualificaCommercialBackOfficeSeeder (who holds the role). Like
 * HrOfficeRoleCatalogue, a module list rather than a mansionario block.
 */
final class CommercialBackOfficeRoleCatalogue
{
    public const string ROLE = 'back-office-commerciale';

    public const string DESCRIPTION = 'Back Office Commerciale';

    /**
     * Granted in full, every ability included (D-2, D-3; contracts D-7).
     *
     * @var array<int, string>
     */
    public const array MODULES = [
        'registries',
        'referents',
        'opportunities',
        'quotes',
        'work-orders',
        'contracts',
    ];

    /**
     * Read by the forms of MODULES outside the for-select pickers (D-4): the
     * category tree of opportunities and quotes, and the field ceiling of the
     * opportunity's Sede operativa. `viewAny` only, so their menu entries,
     * gated on `.view`, stay hidden.
     *
     * @var array<int, string>
     */
    public const array SELECT_ONLY_RESOURCES = [
        'product-categories',
        'operational-sites',
    ];

    /**
     * Listed by name (D-1): the org-chart function and job role they share
     * hold other people too. Martina Mosca joined on top of her coordinator
     * mansione (D-6).
     *
     * @var array<int, string>
     */
    public const array USERS = [
        'lidia.russo@qualificagroup.com',
        'deborah.farina@qualificagroup.com',
        'caterina.maduni@qualificagroup.com',
        'alessia.luongo@qualificagroup.com',
        'lia.palladino@qualificagroup.com',
        'martina.mosca@qualificagroup.com',
    ];
}
