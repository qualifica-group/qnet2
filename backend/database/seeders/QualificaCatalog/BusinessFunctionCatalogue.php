<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The business functions of the client's organisation chart (Pianta Organica
 * 8.09): name => whether it is a business unit (BU) or a business service
 * (BS). Names without the sheet's code prefix ("FOR – Formazione" is
 * "Formazione"), user decision 2026-10-19. The legacy sends its matching
 * functions under these very names, so the import adopts them.
 *
 * "Direzione Generale" is not a sheet of the chart: it is the function of the
 * general manager, above every function's head (user decision 2026-10-19).
 */
final class BusinessFunctionCatalogue
{
    public const string BUSINESS_UNIT = 'business_unit';

    public const string BUSINESS_SERVICE = 'business_service';

    /** The function of the catalogue's "APL" branch and of the APL operators. */
    public const string EMPLOYMENT_AGENCY = 'Agenzia per il Lavoro';

    /** The function whose heads every other function's heads report to. */
    public const string GENERAL_MANAGEMENT = 'Direzione Generale';

    /** The HR office: its people receive the "Risorse Umane" application role (spec 0283). */
    public const string HUMAN_RESOURCES = 'Risorse Umane';

    /** @var array<string, string> */
    public const array FUNCTIONS = [
        'Amministrazione e Contabilità' => self::BUSINESS_SERVICE,
        'Accreditamenti' => self::BUSINESS_SERVICE,
        self::EMPLOYMENT_AGENCY => self::BUSINESS_UNIT,
        'Commerciale' => self::BUSINESS_SERVICE,
        'Finanza Agevolata' => self::BUSINESS_UNIT,
        'Fondi Interprofessionali' => self::BUSINESS_UNIT,
        'Formazione' => self::BUSINESS_UNIT,
        'Gestione Sedi' => self::BUSINESS_SERVICE,
        'Gare' => self::BUSINESS_UNIT,
        'Privacy' => self::BUSINESS_UNIT,
        self::HUMAN_RESOURCES => self::BUSINESS_SERVICE,
        'ISO' => self::BUSINESS_UNIT,
        'Istruzione' => self::BUSINESS_UNIT,
        'Sistemi e Supporto Utenti' => self::BUSINESS_SERVICE,
        'Sviluppo Software' => self::BUSINESS_SERVICE,
        'Marketing' => self::BUSINESS_SERVICE,
        'Progettazione' => self::BUSINESS_UNIT,
        'Qualità' => self::BUSINESS_SERVICE,
        'Segreteria' => self::BUSINESS_SERVICE,
        'Servizi Generali' => self::BUSINESS_SERVICE,
        'Sicurezza' => self::BUSINESS_UNIT,
        'SOA' => self::BUSINESS_UNIT,
        'Unit Travel' => self::BUSINESS_UNIT,
        'UPPCG' => self::BUSINESS_SERVICE,
        self::GENERAL_MANAGEMENT => self::BUSINESS_SERVICE,
    ];
}
