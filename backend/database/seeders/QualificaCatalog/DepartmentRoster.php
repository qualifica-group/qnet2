<?php

namespace Database\Seeders\QualificaCatalog;

use App\Services\FieldChangeRequests\FieldChangeRequestCreator;
use App\Services\RequestManagement\RequestTransferService;
use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;

/**
 * The people of the department questionnaires (spec 0408), by account email:
 * the department and add-on roles they hold (one per department or office
 * they work in, D-8/D-9) and the other roles the questionnaire names ("ADMIN",
 * the notification roles of the notes). Those are all the roles they keep (D-12); nobody holds direct
 * permissions. Whoever is not listed is left alone; the list grows as the
 * other departments answer.
 */
final class DepartmentRoster
{
    /** The notification roles Aliberti and Falzarano keep. */
    private const array SUPERVISOR_NOTIFICATIONS = [FieldChangeRequestCreator::NOTIFIED_ROLE, RequestTransferService::NOTIFIED_ROLE];

    /**
     * @var array<string, array{roles: array<int, string>, other_roles?: array<int, string>}>
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
        'francesca.russodelprete@qualificagroup.com' => ['roles' => [Department::ISO.Department::SENIOR_SUFFIX], 'other_roles' => ['admin']],
        'mario.esposito@qualificagroup.com' => ['roles' => [Department::ISO], 'other_roles' => ['admin']],
        'annarita.palumbo@qualificagroup.com' => ['roles' => [Department::ISO, Department::PLANNING]],
        'consiglia.cosentino@qualificagroup.com' => ['roles' => [Department::ISO, Department::PLANNING]],

        // Sicurezza
        'fernando.annunziata@qualificagroup.com' => ['roles' => [Department::SAFETY.Department::SENIOR_SUFFIX, Department::SAFETY_HEAD, Department::COMMERCIAL]],
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

        // APL (Luca Chiappetti, responsabile, "deve vedere tutto"; Castagna, Romito,
        // Lo Bartolo and Gaspari also in the Commerciale questionnaire)
        'luca.chiappetti@qualificagroup.com' => ['roles' => [Department::APL.Department::SENIOR_SUFFIX]],
        'manuela.rivolta@qualificagroup.com' => ['roles' => [Department::APL]],
        'jessica.faettini@qualificagroup.com' => ['roles' => [Department::APL]],
        'samantha.castagna@qualificagroup.com' => ['roles' => [Department::APL, Department::COMMERCIAL]],
        'emanuele.ascione@qualificagroup.com' => ['roles' => [Department::APL]],
        'gessica.crispo@qualificagroup.com' => ['roles' => [Department::APL]],
        'raffaele.distico@qualificagroup.com' => ['roles' => [Department::APL]],
        'giovanna.gervasio@qualificagroup.com' => ['roles' => [Department::APL]],
        'grazia.triola@qualificagroup.com' => ['roles' => [Department::APL]],
        'desire.romito@qualificagroup.com' => ['roles' => [Department::APL, Department::COMMERCIAL]],
        'daila.lobartolo@qualificagroup.com' => ['roles' => [Department::APL, Department::COMMERCIAL]],
        'alessandra.gaspari@qualificagroup.com' => ['roles' => [Department::APL, Department::COMMERCIAL]],

        // Contabilita' (responsabile Centore and coordinatrice Romano "consultare tutto";
        // Alberto Russo, Gruppo Casiello, has no account yet)
        'raffaele.centore@qualificagroup.it' => ['roles' => [Department::ACCOUNTING.Department::SENIOR_SUFFIX]],
        'bernadetta.romano@qualificagroup.com' => ['roles' => [Department::ACCOUNTING.Department::SENIOR_SUFFIX]],
        'anna.venere@qualificagroup.com' => ['roles' => [Department::ACCOUNTING]],
        'l.manfredonia@studiocasiello.it' => ['roles' => [Department::ACCOUNTING]],

        // Michela Fabozzi, responsabile, "ADMIN" in the Commerciale and Back Office questionnaires
        'michela.fabozzi@qualificagroup.com' => [
            'roles' => [Department::COMMERCIAL.Department::SENIOR_SUFFIX, Department::BACK_OFFICE.Department::SENIOR_SUFFIX],
            'other_roles' => ['admin'],
        ],

        // Back Office (Martina Mosca supervisor, with the team notifications of her note)
        'martina.mosca@qualificagroup.com' => ['roles' => [Department::BACK_OFFICE.Department::SENIOR_SUFFIX, Department::BACK_OFFICE_TEAM_NOTIFICATIONS]],
        'lidia.russo@qualificagroup.com' => ['roles' => [Department::BACK_OFFICE]],
        'deborah.farina@qualificagroup.com' => ['roles' => [Department::BACK_OFFICE]],
        'caterina.maduni@qualificagroup.com' => ['roles' => [Department::BACK_OFFICE]],
        'alessia.luongo@qualificagroup.com' => ['roles' => [Department::BACK_OFFICE]],
        'lia.palladino@qualificagroup.com' => ['roles' => [Department::BACK_OFFICE]],

        // Commerciale supervisors, "notifiche richiesta modifica fonte" (they already
        // received the contact transfer notifications too)
        'fabrizio.aliberti@qualificagroup.com' => ['roles' => [Department::COMMERCIAL.Department::SENIOR_SUFFIX], 'other_roles' => self::SUPERVISOR_NOTIFICATIONS],
        'rosa.falzarano@qualificagroup.com' => ['roles' => [Department::COMMERCIAL.Department::SENIOR_SUFFIX], 'other_roles' => self::SUPERVISOR_NOTIFICATIONS],

        // Commerciale (Antonio Alvoni also the agents' fiscal profiles; "Simone
        // Chiacchio" in the file is Simona). Without an account: Miriam
        // Cantatore, Maddalena Vitale, Imma Pascale.
        'antonio.alvoni@qualificagroup.com' => ['roles' => [Department::COMMERCIAL, Department::COMMERCIAL_FISCAL_PROFILES]],
        'marco.baldi@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'biagio.fusco@qualificagroup.it' => ['roles' => [Department::COMMERCIAL]],
        'marco.fedele@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'antimo.dalvini@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'constantin.popa@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'valentina.scala@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'simona.chiacchio@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'simone.seneca@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'anna.garofalo@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'giulia.costanzo@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'lea.pellegrino@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'gaetano.dellaporta@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'francesca.forgione@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'vincenzo.crisci@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'yailin.calderon@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'luana.logozzo@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'elhajiri.zhour@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'martina.dimarco@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'mariaclelia.bernardi@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'francesca.derrico@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'alessandra.mentella@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'silvia.paternesi@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'silvia.avorio@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'cristina.leotta@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'tania.macale@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'marta.maggio@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'giada.curzola@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'simona.curi@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'gresianovella.cannizzo@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'daniela.petringa@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'jessica.virgolini@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'sarasilvana.cavallo@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'tiziana.digesu@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'eva.spataro@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'valentina.guarino@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'laura.clessidra@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'alessia.margiotta@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'luca.romano@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'andreana.giuliano@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'francesco.dellacorte@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'marilisa.taglialatela@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'sara.armerini@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'francesco.darbitrio@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
        'elena.derosa@qualificagroup.com' => ['roles' => [Department::COMMERCIAL]],
    ];
}
