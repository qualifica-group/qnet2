<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Who belongs to which business function and who heads it, transcribed from
 * the sheets of "Pianta_Organica_Qualifica_Aggiornato 8.09.xlsx", one sheet
 * per function (user directive 2026-10-19). Pure data, read by
 * QualificaHrCatalogSeeder.
 *
 * `heads` are the sheet's "Livello I" rows: the first is the function's
 * manager, all of them are added to the "Risponde a" of the members. A head
 * belongs to the function too. Keyed by account email, matched by name as in
 * OrgChartRoster.
 *
 * As the sheets have it: "Agenzia per il Lavoro" has an empty "Livello I" row
 * and "Segreteria" has none, so both have no head; "Amministrazione e
 * Contabilità" has two, Studio Casiello (CFO, the manager) and Studio
 * Centore (user decision 2026-10-19). Left out: Lorenzo Sepe and Alessandro
 * Zincone, who have no account.
 *
 * Not in the sheets: "Direzione Generale", headed by Enrico Ferrante, who is
 * added to the "Risponde a" of every other function's heads (user decision
 * 2026-10-19).
 */
final class OrgChartFunctions
{
    /**
     * function name => head emails (first = manager), member emails.
     *
     * @var array<string, array{heads: list<string>, members: list<string>}>
     */
    public const array FUNCTIONS = [
        'Amministrazione e Contabilità' => [
            'heads' => ['umberto.casiello@qualificagroup.com', 'raffaele.centore@qualificagroup.it'],
            'members' => [
                'anna.venere@qualificagroup.com',
                'bernadetta.romano@qualificagroup.com',
                'maria.bellini@qualificagroup.com',
            ],
        ],
        'Accreditamenti' => [
            'heads' => ['maria.iannotta@qualificagroup.com'],
            'members' => [
                'giuseppe.agus@qualificagroup.com',
                'giuseppe.tudisco@qualificagroup.com',
                'luigifabio.pescosolido@qualificagroup.com',
            ],
        ],
        BusinessFunctionCatalogue::EMPLOYMENT_AGENCY => [
            'heads' => [],
            'members' => [
                'daila.lobartolo@qualificagroup.com',
                'desire.romito@qualificagroup.com',
                'emanuele.ascione@qualificagroup.com',
                'gessica.crispo@qualificagroup.com',
                'giovanna.gervasio@qualificagroup.com',
                'grazia.triola@qualificagroup.com',
                'jessica.faettini@qualificagroup.com',
                'manuela.rivolta@qualificagroup.com',
                'raffaele.distico@qualificagroup.com',
                'samantha.castagna@qualificagroup.com',
            ],
        ],
        'Commerciale' => [
            'heads' => ['michela.fabozzi@qualificagroup.com'],
            'members' => [
                'alessia.luongo@qualificagroup.com',
                'anna.garofalo@qualificagroup.com',
                'antonino.fasone@qualificagroup.com',
                'antonio.alvoni@qualificagroup.com',
                'biagio.fusco@qualificagroup.it',
                'caterina.maduni@qualificagroup.com',
                'constantin.popa@qualificagroup.com',
                'deborah.farina@qualificagroup.com',
                'elhajiri.zhour@qualificagroup.com',
                'fabrizio.aliberti@qualificagroup.com',
                'fernando.annunziata@qualificagroup.com',
                'francesca.forgione@qualificagroup.com',
                'gaetano.dellaporta@qualificagroup.com',
                'giulia.costanzo@qualificagroup.com',
                'giuseppe.gallo@qualificagroup.com',
                'lea.pellegrino@qualificagroup.com',
                'lia.palladino@qualificagroup.com',
                'lidia.russo@qualificagroup.com',
                'luana.logozzo@qualificagroup.com',
                'marco.baldi@qualificagroup.com',
                'marco.fedele@qualificagroup.com',
                'martina.mosca@qualificagroup.com',
                'roberto.rubino@qualificagroup.com',
                'rosa.falzarano@qualificagroup.com',
                'simona.chiacchio@qualificagroup.com',
                'simone.seneca@qualificagroup.com',
                'valentina.scala@qualificagroup.com',
                'vincenzo.crisci@qualificagroup.com',
            ],
        ],
        'Finanza Agevolata' => [
            'heads' => ['ferdinando.flagiello@qualificagroup.com'],
            'members' => [
                'federica.degennaro@qualificagroup.com',
                'mariarita.balzamo@qualificagroup.com',
                'rita.carrese@qualificagroup.com',
            ],
        ],
        'Fondi Interprofessionali' => [
            'heads' => ['roberta.apperti@qualificagroup.com'],
            'members' => [
            ],
        ],
        'Formazione' => [
            'heads' => ['jessica.chentrens@qualificagroup.com'],
            'members' => [
                'alessandra.mentella@qualificagroup.com',
                'alessia.margiotta@qualificagroup.com',
                'anastasia.marcacci@qualificagroup.com',
                'andreana.giuliano@qualificagroup.com',
                'annalisa.giordano@qualificagroup.com',
                'chiara.centracchio@qualificagroup.com',
                'cristina.leotta@qualificagroup.com',
                'daniela.petringa@qualificagroup.com',
                'desire.romito@qualificagroup.com',
                'elena.derosa@qualificagroup.com',
                'emilianna.credentino@qualificagroup.com',
                'eva.spataro@qualificagroup.com',
                'francesca.derrico@qualificagroup.com',
                'francesco.dellacorte@qualificagroup.com',
                'giada.curzola@qualificagroup.com',
                'gresianovella.cannizzo@qualificagroup.com',
                'ida.panico@qualificagroup.com',
                'ilaria.striano@qualificagroup.com',
                'laura.allegretta@qualificagroup.com',
                'luca.romano@qualificagroup.com',
                'maria.dallara@qualificagroup.com',
                'mariaclelia.bernardi@qualificagroup.com',
                'marilisa.taglialatela@qualificagroup.com',
                'marlena.jaruga@qualificagroup.com',
                'marta.maggio@qualificagroup.com',
                'martina.dimarco@qualificagroup.com',
                'michela.poti@qualificagroup.com',
                'michele.vitale@qualificagroup.com',
                'nicoletta.esposito@qualificagroup.com',
                'patricia.christovao@qualificagroup.com',
                'rita.carrese@qualificagroup.com',
                'samantha.castagna@qualificagroup.com',
                'sarasilvana.cavallo@qualificagroup.com',
                'tiziana.digesu@qualificagroup.com',
                'yailin.calderon@qualificagroup.com',
            ],
        ],
        'Gestione Sedi' => [
            'heads' => ['luca.ponticelli@qualificagroup.com'],
            'members' => [
                'luigifabio.pescosolido@qualificagroup.com',
            ],
        ],
        'Gare' => [
            'heads' => ['maria.iannotta@qualificagroup.com'],
            'members' => [
                'pina.derosa@qualificagroup.com',
            ],
        ],
        'Privacy' => [
            'heads' => ['francesca.russodelprete@qualificagroup.com'],
            'members' => [
                'antonio.demartino@qualificagroup.com',
                'antoniosalvatore.peluso@qualificagroup.com',
            ],
        ],
        BusinessFunctionCatalogue::HUMAN_RESOURCES => [
            'heads' => ['anna.castaldo@qualificagroup.com'],
            'members' => [
                'amelia.iorio@qualificagroup.com',
                'valentina.sorrentino@qualificagroup.com',
            ],
        ],
        'ISO' => [
            'heads' => ['francesca.russodelprete@qualificagroup.com'],
            'members' => [
                'annarita.palumbo@qualificagroup.com',
                'consiglia.cosentino@qualificagroup.com',
                'edvige.graziano@qualificagroup.com',
                'francesco.ienco@qualificagroup.com',
                'ilaria.vitolo@qualificagroup.com',
                'ivana.mango@qualificagroup.com',
                'maria.savarese@qualificagroup.com',
                'mario.esposito@qualificagroup.com',
                'noemi.nigliato@qualificagroup.it',
                'paola.sena@qualificagroup.com',
                'raffaele.nocerino@qualificagroup.com',
            ],
        ],
        'Istruzione' => [
            'heads' => ['ferdinando.flagiello@qualificagroup.com'],
            'members' => [
                'antonio.foglia@qualificagroup.com',
                'rosa.pagliara@qualificagroup.com',
            ],
        ],
        'Sistemi e Supporto Utenti' => [
            'heads' => ['lillo.irrera@qualificagroup.com'],
            'members' => [
                'flavio.scognamiglio@qualificagroup.com',
            ],
        ],
        'Sviluppo Software' => [
            'heads' => ['ciro.cacciapuoti@qualificagroup.com'],
            'members' => [
                'andrea.arena@qualificagroup.com',
                'nicola.eliseo@qualificagroup.com',
                'nicolo.caruso@qualificagroup.com',
            ],
        ],
        'Marketing' => [
            'heads' => ['umberto.santamaria@qualificagroup.com'],
            'members' => [
                'claudia.testa@qualificagroup.com',
                'felice.salomone@qualificagroup.com',
                'miriam.dellaversana@qualificagroup.com',
                'sabino.figurelli@qualificagroup.com',
            ],
        ],
        'Progettazione' => [
            'heads' => ['ferdinando.flagiello@qualificagroup.com'],
            'members' => [
                'giuseppe.agus@qualificagroup.com',
                'rosa.pagliara@qualificagroup.com',
            ],
        ],
        'Qualità' => [
            'heads' => ['mario.esposito@qualificagroup.com'],
            'members' => [
            ],
        ],
        'Segreteria' => [
            'heads' => [],
            'members' => [
                'anna.dimaio@qualificagroup.com',
                'stefania.silvestro@qualificagroup.com',
                'valentina.cavaliere@qualificagroup.com',
            ],
        ],
        'Servizi Generali' => [
            'heads' => ['luca.ponticelli@qualificagroup.com'],
            'members' => [
            ],
        ],
        'Sicurezza' => [
            'heads' => ['fernando.annunziata@qualificagroup.com'],
            'members' => [
                'elio.rettori@qualificagroup.com',
                'francesco.dimaio@qualificagroup.com',
                'gaetano.farina@qualificagroup.com',
                'pasquale.orsusanno@qualificagroup.com',
            ],
        ],
        'SOA' => [
            'heads' => ['maria.iannotta@qualificagroup.com'],
            'members' => [
                'francesca.mauriello@qualificagroup.com',
                'roberto.sepe@qualificagroup.com',
            ],
        ],
        'Unit Travel' => [
            'heads' => ['nunzio.guida@qualificagroup.com'],
            'members' => [
                'maria.pezzella@qualificagroup.com',
            ],
        ],
        'UPPCG' => [
            'heads' => ['luca.ponticelli@qualificagroup.com'],
            'members' => [
                'luigifabio.pescosolido@qualificagroup.com',
            ],
        ],
        BusinessFunctionCatalogue::GENERAL_MANAGEMENT => [
            'heads' => [self::GENERAL_MANAGER],
            'members' => [
            ],
        ],
    ];

    /** Not in OrgChartRoster: the sheets give him no role or level. */
    public const string GENERAL_MANAGER = 'enrico.ferrante@qualificagroup.com';
}
