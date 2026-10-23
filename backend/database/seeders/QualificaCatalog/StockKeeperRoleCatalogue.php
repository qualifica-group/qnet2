<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The equipment keeper's application role (spec 0307 D-21, user directive
 * 2026-10-23). Pure data, read by QualificaRoleSeeder (the matrix) and
 * QualificaStockKeeperSeeder (who holds the role). The pickers of the movement
 * form use the for-selects, which need no other permission.
 */
final class StockKeeperRoleCatalogue
{
    public const string ROLE = 'gestione-dotazioni';

    public const string DESCRIPTION = 'Gestione Dotazioni';

    /**
     * Granted in full, every ability included.
     *
     * @var array<int, string>
     */
    public const array MODULES = ['stock'];

    /**
     * @var array<int, string>
     */
    public const array USERS = ['flavio.scognamiglio@qualificagroup.com'];
}
