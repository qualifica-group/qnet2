<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;

/**
 * The people of the department questionnaires (spec 0408), by account email:
 * the department and add-on roles they hold (one per department or office
 * they work in, D-8/D-9) and the replaced roles they keep. Nobody holds direct
 * permissions. Whoever is not listed is left alone; the list grows as the
 * other departments answer.
 */
final class DepartmentRoster
{
    /**
     * @var array<string, array{roles: array<int, string>, keep?: array<int, string>}>
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
        'francesca.russodelprete@qualificagroup.com' => ['roles' => [Department::ISO.Department::SENIOR_SUFFIX], 'keep' => ['admin']],
        'mario.esposito@qualificagroup.com' => ['roles' => [Department::ISO], 'keep' => ['admin']],
        'annarita.palumbo@qualificagroup.com' => ['roles' => [Department::ISO, Department::PLANNING]],
        'consiglia.cosentino@qualificagroup.com' => ['roles' => [Department::ISO, Department::PLANNING]],

        // Sicurezza
        'fernando.annunziata@qualificagroup.com' => ['roles' => [Department::SAFETY.Department::SENIOR_SUFFIX, Department::SAFETY_HEAD]],
        'pasquale.orsusanno@qualificagroup.com' => ['roles' => [Department::SAFETY.Department::SENIOR_SUFFIX]],
        'francesco.dimaio@qualificagroup.com' => ['roles' => [Department::SAFETY]],
        'elio.rettori@qualificagroup.com' => ['roles' => [Department::SAFETY]],
        'gaetano.farina@qualificagroup.com' => ['roles' => [Department::SAFETY]],

        // Travel
        'maria.pezzella@qualificagroup.com' => ['roles' => [Department::TRAVEL]],

        // Accreditamenti e Gestione Sedi (Agus also in Finanza Agevolata and Progettazione, "SOLO COMMESSE")
        'luigifabio.pescosolido@qualificagroup.com' => ['roles' => [Department::ACCREDITATION]],
        'giuseppe.agus@qualificagroup.com' => ['roles' => [Department::ACCREDITATION, Department::SUBSIDISED_FINANCE, Department::PROJECT_DESIGN]],

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

        // APL (Luca Chiappetti, responsabile, "deve vedere tutto")
        'luca.chiappetti@qualificagroup.com' => ['roles' => [Department::APL.Department::SENIOR_SUFFIX]],
        'manuela.rivolta@qualificagroup.com' => ['roles' => [Department::APL]],
        'jessica.faettini@qualificagroup.com' => ['roles' => [Department::APL]],
        'samantha.castagna@qualificagroup.com' => ['roles' => [Department::APL]],
        'emanuele.ascione@qualificagroup.com' => ['roles' => [Department::APL]],
        'gessica.crispo@qualificagroup.com' => ['roles' => [Department::APL]],
        'raffaele.distico@qualificagroup.com' => ['roles' => [Department::APL]],
        'giovanna.gervasio@qualificagroup.com' => ['roles' => [Department::APL]],
        'grazia.triola@qualificagroup.com' => ['roles' => [Department::APL]],
        'desire.romito@qualificagroup.com' => ['roles' => [Department::APL]],
        'daila.lobartolo@qualificagroup.com' => ['roles' => [Department::APL]],
        'alessandra.gaspari@qualificagroup.com' => ['roles' => [Department::APL]],

        // Contabilita' (responsabile Centore and coordinatrice Romano "consultare tutto";
        // Alberto Russo, Gruppo Casiello, has no account yet)
        'raffaele.centore@qualificagroup.it' => ['roles' => [Department::ACCOUNTING.Department::SENIOR_SUFFIX]],
        'bernadetta.romano@qualificagroup.com' => ['roles' => [Department::ACCOUNTING.Department::SENIOR_SUFFIX]],
        'anna.venere@qualificagroup.com' => ['roles' => [Department::ACCOUNTING]],
        'l.manfredonia@studiocasiello.it' => ['roles' => [Department::ACCOUNTING]],
    ];
}
