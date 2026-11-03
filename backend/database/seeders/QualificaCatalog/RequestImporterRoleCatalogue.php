<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * "Importa richieste" (spec 0348, user directive 2026-11-03): the Excel import
 * of the trattative is usable by `super-admin` and by the people given this
 * role in OperatorRoster::EXTRA_ROLES only. It is a role of its own, held ON TOP of the person's mansione, so that
 * no mansione (nor `admin`) carries `request-management.import` through its
 * module grant.
 */
final class RequestImporterRoleCatalogue
{
    public const string ROLE = 'importatore-richieste';

    public const string DESCRIPTION = 'Importatore richieste';

    public const string PERMISSION = 'request-management.import';
}
