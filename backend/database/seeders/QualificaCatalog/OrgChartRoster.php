<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Who holds which job role, transcribed from the client's
 * "Elenco_Dipendenti_Qualifica_Aggiornato 8.csv" (Mansione, Email aziendale),
 * user directive 2026-10-19. Pure data, read by QualificaHrCatalogSeeder.
 *
 * Keyed by account email, like the other rosters: the sheets spell names
 * their own way ("Letterio" for Lillo Irrera, "Giuseppina" for Pina De Rosa,
 * "Studio Centore" for Raffaele Centore), the email is the stable key.
 *
 * Normalised at transcription, like JobRoleCatalogue: role names are the
 * catalogue's (no site or region, a "+" role split in two); the CSV
 * emails are the shared mailboxes the person answers, the account's own
 * email left out ("g.gervasio@qualificagroup.itcv@qualificagroup.it" in the
 * CSV is two mailboxes run together).
 *
 * Deliberately left out: Lorenzo Sepe and Alessandro Zincone, who have no
 * account.
 */
final class OrgChartRoster
{
    /**
     * The external accountancy firms: their condition is "Altro", not "Dipendente".
     *
     * @var list<string>
     */
    public const array EXTERNAL = [
        'raffaele.centore@qualificagroup.it',
        'umberto.casiello@qualificagroup.com',
    ];

    /**
     * account email => job role names, additional work emails.
     *
     * @var array<string, array{roles: list<string>, emails: list<string>}>
     */
    public const array PEOPLE = [
        'alessandra.mentella@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['a.mentella@qualificagroup.it']],
        'alessia.luongo@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'emails' => ['backoffice3@qualificagroup.it']],
        'alessia.margiotta@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['sicilia@qualificagroup.it']],
        'amelia.iorio@qualificagroup.com' => ['roles' => ['Consulente del lavoro'], 'emails' => ['a.iorio@qualificagroup.it']],
        'anastasia.marcacci@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative'], 'emails' => ['casilina@qualificagroup.it']],
        'andrea.arena@qualificagroup.com' => ['roles' => ['Sviluppatore Software'], 'emails' => ['a.arena@qualificagroup.it']],
        'andreana.giuliano@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['nola@qualificagroup.it']],
        'anna.castaldo@qualificagroup.com' => ['roles' => ['HR Manager / Responsabile Settore HR'], 'emails' => ['a.castaldo@qualificagroup.it', 'personale@qualificagroup.it', 'cv@qualificagroup.it']],
        'anna.dimaio@qualificagroup.com' => ['roles' => ['Addetto Segreteria'], 'emails' => ['backoffice_casoria@qualificagroup.it']],
        'anna.garofalo@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['commerciale2@qualificagroup.it']],
        'anna.venere@qualificagroup.com' => ['roles' => ['Addetto Recupero Crediti e Fatturazione'], 'emails' => ['contab3@qualificagroup.it']],
        'annalisa.giordano@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['nola@qualificagroup.it']],
        'annarita.palumbo@qualificagroup.com' => ['roles' => ['Addetto Pianificazione e Controllo'], 'emails' => ['pianificazione2@qualificagroup.it']],
        'antonino.fasone@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['a.fasone@qualificagroup.it']],
        'antonio.alvoni@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['a.alvoni@qualificagroup.it']],
        'antonio.demartino@qualificagroup.com' => ['roles' => ['Consulente GDPR/Privacy'], 'emails' => ['a.dimartino@qualificagroup.it']],
        'antonio.foglia@qualificagroup.com' => ['roles' => ['Consulente Istruzione'], 'emails' => []],
        'antoniosalvatore.peluso@qualificagroup.com' => ['roles' => ['Consulente GDPR/Privacy'], 'emails' => ['salvatore.consulenza@qualificagroup.it']],
        'bernadetta.romano@qualificagroup.com' => ['roles' => ['Coordinatore Contabilità'], 'emails' => ['b.romano@qualificagroup.it', 'contabilita@qualificagroup.it']],
        'biagio.fusco@qualificagroup.it' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => []],
        'caterina.maduni@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'emails' => ['convenzionigol2@qualificagroup.it']],
        'chiara.centracchio@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['isernia@qualificagroup.it']],
        'ciro.cacciapuoti@qualificagroup.com' => ['roles' => ['Responsabile Sviluppo Software'], 'emails' => []],
        'claudia.testa@qualificagroup.com' => ['roles' => ['Graphic Designer'], 'emails' => ['design@qualificagroup.it']],
        'consiglia.cosentino@qualificagroup.com' => ['roles' => ['Addetto Pianificazione e Controllo'], 'emails' => ['pianificazione@qualificagroup.it']],
        'constantin.popa@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['c.popa@qualificagroup.it']],
        'cristina.leotta@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['bergamo2@qualificagroup.it']],
        'daila.lobartolo@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'emails' => ['gol.mazzarino@qualificagroup.it']],
        'daniela.petringa@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['catania@qualificagroup.it']],
        'deborah.farina@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'emails' => ['backoffice2@qualificagroup.it', 'offerte@qualificagroup.it']],
        'desire.romito@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive', 'Segreteria Didattica'], 'emails' => ['viterbo@qualificagroup.it']],
        'edvige.graziano@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => ['edvige.consulenza@qualificagroup.it']],
        'elena.derosa@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['cardito@qualificagroup.it']],
        'elhajiri.zhour@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['bologna@qualificagroup.it']],
        'elio.rettori@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'emails' => ['sicurezza2@qualificagroup.it']],
        'emanuele.ascione@qualificagroup.com' => ['roles' => ['Supervisor Operatori Politiche Attive', 'Addetto Ricerca e Selezione'], 'emails' => ['e.ascione@qualificagroup.it']],
        'emilianna.credentino@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['caserta2@qualificagroup.it']],
        'eva.spataro@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['comiso@qualificagroup.it']],
        'fabrizio.aliberti@qualificagroup.com' => ['roles' => ['Supervisor Commerciale Sedi Territoriali'], 'emails' => ['supportocommerciale@qualificagroup.it']],
        'federica.degennaro@qualificagroup.com' => ['roles' => ['Consulente Finanza Agevolata'], 'emails' => ['f.degennaro@qualificagroup.it']],
        'felice.salomone@qualificagroup.com' => ['roles' => ['Web Master / SEO Specialist'], 'emails' => ['web@qualificagroup.it']],
        'ferdinando.flagiello@qualificagroup.com' => ['roles' => ['Responsabile Finanza Agevolata', 'Responsabile Istruzione', 'Responsabile Progettazione'], 'emails' => ['f.flagiello@qualificagroup.it', 'istruzione@qualificagroup.it']],
        'fernando.annunziata@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior', 'Responsabile Sicurezza'], 'emails' => ['sicurezza@qualificagroup.it', 'retecommerciale@qualificagroup.it']],
        'flavio.scognamiglio@qualificagroup.com' => ['roles' => ['Tecnico Sistemi e Supporto Utenti'], 'emails' => ['f.scognamiglio@qualificagroup.it']],
        'francesca.derrico@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['f.derrico@qualificagroup.it']],
        'francesca.forgione@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['benevento@qualificagroup.it']],
        'francesca.mauriello@qualificagroup.com' => ['roles' => ['Supporto Operativo SOA'], 'emails' => ['f.mauriello@qualificagroup.it']],
        'francesca.russodelprete@qualificagroup.com' => ['roles' => ['Responsabile GDPR/Privacy', 'Responsabile ISO'], 'emails' => ['ufficioqualita@qualificagroup.it']],
        'francesco.dellacorte@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['casaldiprincipe@qualificagroup.it']],
        'francesco.dimaio@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'emails' => ['sicurezza5@qualificagroup.it']],
        'francesco.ienco@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => ['francesco.consulenza@qualificagroup.it']],
        'gaetano.dellaporta@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['g.dellaporta@qualificagroup.it']],
        'gaetano.farina@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'emails' => ['g.farina@qualificagroup.it', 'sicurezza1@qualificagroup.it']],
        'gessica.crispo@qualificagroup.com' => ['roles' => ['Supervisor Operatori Politiche Attive'], 'emails' => ['g.crispo@qualificagroup.it']],
        'giada.curzola@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['pomezia@qualificagroup.it']],
        'giovanna.gervasio@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive', 'Addetto Ricerca e Selezione'], 'emails' => ['g.gervasio@qualificagroup.it', 'cv@qualificagroup.it']],
        'giulia.costanzo@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['assistenzacommerciale@qualificagroup.it']],
        'giuseppe.agus@qualificagroup.com' => ['roles' => ['Referente Normativo', 'Addetto Progettazione'], 'emails' => ['altaformazione@qualificagroup.it']],
        'giuseppe.gallo@qualificagroup.com' => ['roles' => ['Supervisor Commerciale Sedi Territoriali'], 'emails' => ['g.gallo@qualificagroup.it']],
        'giuseppe.tudisco@qualificagroup.com' => ['roles' => ['Referente Legale'], 'emails' => ['avv.tudisco@qualificagroup.it']],
        'grazia.triola@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'emails' => ['backoffice.aversa@qualificagroup.it']],
        'gresianovella.cannizzo@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['catania2@qualificagroup.it']],
        'ida.panico@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative'], 'emails' => ['gol@qualificagroup.it']],
        'ilaria.striano@qualificagroup.com' => ['roles' => ['Coordinatore Segreteria Didattica'], 'emails' => ['segreteriadidattica@qualificagroup.it']],
        'ilaria.vitolo@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => ['i.vitolo@qualificagroup.it']],
        'ivana.mango@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => []],
        'jessica.chentrens@qualificagroup.com' => ['roles' => ['Responsabile Formazione'], 'emails' => ['direzionedidattica@qualificagroup.it', 'formazione@qualificagroup.it']],
        'jessica.faettini@qualificagroup.com' => ['roles' => ['Referente Territoriale Politiche Attive'], 'emails' => ['j.faettini@qualificagroup.it']],
        'laura.allegretta@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative'], 'emails' => ['l.allegretta@qualificagroup.it']],
        'lea.pellegrino@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['l.pellegrino@qualificagroup.it']],
        'lia.palladino@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'emails' => ['l.palladino@qualificagroup.it']],
        'lidia.russo@qualificagroup.com' => ['roles' => ['Supervisor Back Office Commerciale'], 'emails' => ['backoffice@qualificagroup.it', 'offerte@qualificagroup.it']],
        'lillo.irrera@qualificagroup.com' => ['roles' => ['Responsabile Sistemi e Supporto Utenti'], 'emails' => ['it@qualificagroup.it']],
        'luana.logozzo@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['bergamo3@qualificagroup.it']],
        'luca.ponticelli@qualificagroup.com' => ['roles' => ['Responsabile Gestione Sedi', 'Responsabile Servizi Generali', 'Responsabile UPPCG'], 'emails' => ['l.ponticelli@qualificagroup.it']],
        'luca.romano@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['casalnuovo@qualificagroup.it']],
        'luigifabio.pescosolido@qualificagroup.com' => ['roles' => ['Operativo Ufficio Accreditamenti', 'Operativo Gestione Sedi', 'Supporto Operativo UPPCG'], 'emails' => ['luigi.fabio@qualificagroup.it', 'rendicontazione.sedi@qualificagroup.it']],
        'manuela.rivolta@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'emails' => ['aplmilano@qualificagroup.it']],
        'marco.baldi@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['customer@qualificagroup.it']],
        'marco.fedele@qualificagroup.com' => ['roles' => ['Consulente Commerciale'], 'emails' => ['m.fedele@qualificagroup.it']],
        'maria.bellini@qualificagroup.com' => ['roles' => ['Addetto Contabilità Fornitori e IVA'], 'emails' => ['m.bellini@qualificagroup.it']],
        'maria.dallara@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['palermo@qualificagroup.it']],
        'maria.iannotta@qualificagroup.com' => ['roles' => ['Responsabile Accreditamenti', 'Responsabile Gare', 'Responsabile SOA'], 'emails' => ['soa@qualificagroup.it']],
        'maria.pezzella@qualificagroup.com' => ['roles' => ['Travel Assistant'], 'emails' => ['m.pezzella@qualificagroup.it']],
        'maria.savarese@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => ['m.savarese@qualificagroup.it']],
        'mariaclelia.bernardi@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['casilina2@qualificagroup.it']],
        'mariarita.balzamo@qualificagroup.com' => ['roles' => ['Supporto Operativo Bandi'], 'emails' => ['m.balzamo@qualificagroup.it']],
        'marilisa.taglialatela@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['giugliano@qualificagroup.it']],
        'mario.esposito@qualificagroup.com' => ['roles' => ['Consulente ISO', 'Responsabile Qualità'], 'emails' => ['m.esposito@qualificagroup.it']],
        'marlena.jaruga@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative', 'Coordinatore Segreteria Didattica'], 'emails' => ['roma@qualificagroup.it']],
        'marta.maggio@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['nettuno@qualificagroup.it']],
        'martina.dimarco@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['viterbo2@qualificagroup.it']],
        'martina.mosca@qualificagroup.com' => ['roles' => ['Supervisor Back Office Commerciale'], 'emails' => ['assistenza@qualificagroup.it']],
        'michela.fabozzi@qualificagroup.com' => ['roles' => ['Responsabile Commerciale'], 'emails' => ['asscommerciale@qualificagroup.it', 'retecommerciale@qualificagroup.it']],
        'michela.poti@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['milano2@qualificagroup.it']],
        'michele.vitale@qualificagroup.com' => ['roles' => ['Coordinatore Segreteria Didattica'], 'emails' => ['percorsifinanziati@qualificagroup.it']],
        'miriam.dellaversana@qualificagroup.com' => ['roles' => ['Social Media Manager Junior'], 'emails' => ['social3@qualificagroup.it']],
        'nicola.eliseo@qualificagroup.com' => ['roles' => ['Sviluppatore Software'], 'emails' => []],
        'nicoletta.esposito@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative', 'Coordinatore Segreteria Didattica'], 'emails' => ['n.esposito@qualificagroup.it']],
        'nicolo.caruso@qualificagroup.com' => ['roles' => ['Sviluppatore Software'], 'emails' => []],
        'noemi.nigliato@qualificagroup.it' => ['roles' => ['Consulente ISO'], 'emails' => []],
        'nunzio.guida@qualificagroup.com' => ['roles' => ['Direttore Tecnico – Responsabile Unit Travel'], 'emails' => ['n.guida@qualificagroup.it']],
        'paola.sena@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => ['paola.consulenza@qualificagroup.it']],
        'pasquale.orsusanno@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'emails' => ['sicurezza3@qualificagroup.it']],
        'patricia.christovao@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['basilicata@qualificagroup.it']],
        'pina.derosa@qualificagroup.com' => ['roles' => ['Tecnico Gare'], 'emails' => ['gare@qualificagroup.it', 'ufficiogare@qualificagroup.it', 'avvalimenti@qualificagroup.it']],
        'raffaele.centore@qualificagroup.it' => ['roles' => ['Commercialista Esterno'], 'emails' => []],
        'raffaele.distico@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'emails' => ['r.distico@qualificagroup.it']],
        'raffaele.nocerino@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'emails' => ['raffaele.consulenza@qualificagroup.it']],
        'rita.carrese@qualificagroup.com' => ['roles' => ['Supporto Operativo Bandi', 'Segreteria Didattica'], 'emails' => ['aversa@qualificagroup.it']],
        'roberta.apperti@qualificagroup.com' => ['roles' => ['Responsabile Fondi Interprofessionali'], 'emails' => ['r.apperti@qualificagroup.it']],
        'roberto.rubino@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['r.rubino@qualificagroup.it']],
        'roberto.sepe@qualificagroup.com' => ['roles' => ['Tecnico SOA'], 'emails' => ['soa2@qualificagroup.it']],
        'rosa.falzarano@qualificagroup.com' => ['roles' => ['Supervisor Commerciale Sedi Territoriali'], 'emails' => ['commercialegol@qualificagroup.it']],
        'rosa.pagliara@qualificagroup.com' => ['roles' => ['Addetto Progettazione', 'Consulente Istruzione'], 'emails' => ['r.pagliara@qualificagroup.it']],
        'sabino.figurelli@qualificagroup.com' => ['roles' => ['Social Media Manager Junior'], 'emails' => ['social2@qualificagroup.it']],
        'samantha.castagna@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive', 'Segreteria Didattica'], 'emails' => ['bergamo@qualificagroup.it']],
        'sarasilvana.cavallo@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['gela@qualificagroup.it']],
        'simona.chiacchio@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['convenzionigol@qualificagroup.it']],
        'simone.seneca@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['s.seneca@qualificagroup.it']],
        'stefania.silvestro@qualificagroup.com' => ['roles' => ['Addetto Segreteria'], 'emails' => ['segreteria@qualificagroup.it', 'acquisti@qualificagroup.it']],
        'tiziana.digesu@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['gela@qualificagroup.it']],
        'umberto.casiello@qualificagroup.com' => ['roles' => ['CFO', 'Supporto Operativo Amministrazione'], 'emails' => ['umberto.casiello@qualificagroup.it']],
        'umberto.santamaria@qualificagroup.com' => ['roles' => ['Responsabile Marketing'], 'emails' => ['social@qualificagroup.it']],
        'valentina.cavaliere@qualificagroup.com' => ['roles' => ['Addetto Segreteria'], 'emails' => ['segreteria2@qualificagroup.it']],
        'valentina.scala@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'emails' => ['collaborazioni@qualificagroup.it']],
        'valentina.sorrentino@qualificagroup.com' => ['roles' => ['HR Generalist'], 'emails' => ['v.sorrentino@qualificagroup.it', 'cv@qualificagroup.it']],
        'vincenzo.crisci@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'emails' => ['v.crisci@qualificagroup.it']],
        'yailin.calderon@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'emails' => ['grumellodelmonte@qualificagroup.it']],
    ];
}
