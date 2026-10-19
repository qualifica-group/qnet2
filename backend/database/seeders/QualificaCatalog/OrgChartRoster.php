<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Who holds which job role and level, transcribed from the client's
 * "Elenco_Dipendenti_Qualifica_Aggiornato 8.csv" (Mansione, Email aziendale)
 * and "Pianta_Organica_Qualifica_Aggiornato 8.09.xlsx" (Livello), user
 * directive 2026-10-19. Pure data, read by QualificaHrCatalogSeeder.
 *
 * Keyed by account email, like the other rosters: the sheets spell names
 * their own way ("Letterio" for Lillo Irrera, "Giuseppina" for Pina De Rosa,
 * "Studio Centore" for Raffaele Centore), the email is the stable key.
 *
 * Normalised at transcription, like JobRoleCatalogue: role names are the
 * catalogue's (no site or region, a "+" role split in two); a person listed
 * under several sectors with different levels takes the highest one; the CSV
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
     * account email => job role names, CCNL level code, additional work emails.
     *
     * @var array<string, array{roles: list<string>, level: string, emails: list<string>}>
     */
    public const array PEOPLE = [
        'alessandra.mentella@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['a.mentella@qualificagroup.it']],
        'alessia.luongo@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'level' => 'Livello III', 'emails' => ['backoffice3@qualificagroup.it']],
        'alessia.margiotta@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['sicilia@qualificagroup.it']],
        'amelia.iorio@qualificagroup.com' => ['roles' => ['Consulente del lavoro'], 'level' => 'Livello III', 'emails' => ['a.iorio@qualificagroup.it']],
        'anastasia.marcacci@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative'], 'level' => 'Livello II', 'emails' => ['casilina@qualificagroup.it']],
        'andrea.arena@qualificagroup.com' => ['roles' => ['Sviluppatore Software'], 'level' => 'Livello III', 'emails' => ['a.arena@qualificagroup.it']],
        'andreana.giuliano@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['nola@qualificagroup.it']],
        'anna.castaldo@qualificagroup.com' => ['roles' => ['HR Manager / Responsabile Settore HR'], 'level' => 'Livello I', 'emails' => ['a.castaldo@qualificagroup.it', 'personale@qualificagroup.it', 'cv@qualificagroup.it']],
        'anna.dimaio@qualificagroup.com' => ['roles' => ['Addetto Segreteria'], 'level' => 'Livello III', 'emails' => ['backoffice_casoria@qualificagroup.it']],
        'anna.garofalo@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['commerciale2@qualificagroup.it']],
        'anna.venere@qualificagroup.com' => ['roles' => ['Addetto Recupero Crediti e Fatturazione'], 'level' => 'Livello III', 'emails' => ['contab3@qualificagroup.it']],
        'annalisa.giordano@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['nola@qualificagroup.it']],
        'annarita.palumbo@qualificagroup.com' => ['roles' => ['Addetto Pianificazione e Controllo'], 'level' => 'Livello III', 'emails' => ['pianificazione2@qualificagroup.it']],
        'antonino.fasone@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['a.fasone@qualificagroup.it']],
        'antonio.alvoni@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['a.alvoni@qualificagroup.it']],
        'antonio.demartino@qualificagroup.com' => ['roles' => ['Consulente GDPR/Privacy'], 'level' => 'Livello III', 'emails' => ['a.dimartino@qualificagroup.it']],
        'antonio.foglia@qualificagroup.com' => ['roles' => ['Consulente Istruzione'], 'level' => 'Livello III', 'emails' => []],
        'antoniosalvatore.peluso@qualificagroup.com' => ['roles' => ['Consulente GDPR/Privacy'], 'level' => 'Livello III', 'emails' => ['salvatore.consulenza@qualificagroup.it']],
        'bernadetta.romano@qualificagroup.com' => ['roles' => ['Coordinatore Contabilità'], 'level' => 'Livello II', 'emails' => ['b.romano@qualificagroup.it', 'contabilita@qualificagroup.it']],
        'biagio.fusco@qualificagroup.it' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => []],
        'caterina.maduni@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'level' => 'Livello III', 'emails' => ['convenzionigol2@qualificagroup.it']],
        'chiara.centracchio@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['isernia@qualificagroup.it']],
        'ciro.cacciapuoti@qualificagroup.com' => ['roles' => ['Responsabile Sviluppo Software'], 'level' => 'Livello I', 'emails' => []],
        'claudia.testa@qualificagroup.com' => ['roles' => ['Graphic Designer'], 'level' => 'Livello III', 'emails' => ['design@qualificagroup.it']],
        'consiglia.cosentino@qualificagroup.com' => ['roles' => ['Addetto Pianificazione e Controllo'], 'level' => 'Livello III', 'emails' => ['pianificazione@qualificagroup.it']],
        'constantin.popa@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['c.popa@qualificagroup.it']],
        'cristina.leotta@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['bergamo2@qualificagroup.it']],
        'daila.lobartolo@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'level' => 'Livello III', 'emails' => ['gol.mazzarino@qualificagroup.it']],
        'daniela.petringa@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['catania@qualificagroup.it']],
        'deborah.farina@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'level' => 'Livello III', 'emails' => ['backoffice2@qualificagroup.it', 'offerte@qualificagroup.it']],
        'desire.romito@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive', 'Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['viterbo@qualificagroup.it']],
        'edvige.graziano@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => ['edvige.consulenza@qualificagroup.it']],
        'elena.derosa@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['cardito@qualificagroup.it']],
        'elhajiri.zhour@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['bologna@qualificagroup.it']],
        'elio.rettori@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'level' => 'Livello III', 'emails' => ['sicurezza2@qualificagroup.it']],
        'emanuele.ascione@qualificagroup.com' => ['roles' => ['Supervisor Operatori Politiche Attive', 'Addetto Ricerca e Selezione'], 'level' => 'Livello II', 'emails' => ['e.ascione@qualificagroup.it']],
        'emilianna.credentino@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['caserta2@qualificagroup.it']],
        'eva.spataro@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['comiso@qualificagroup.it']],
        'fabrizio.aliberti@qualificagroup.com' => ['roles' => ['Supervisor Commerciale Sedi Territoriali'], 'level' => 'Livello II', 'emails' => ['supportocommerciale@qualificagroup.it']],
        'federica.degennaro@qualificagroup.com' => ['roles' => ['Consulente Finanza Agevolata'], 'level' => 'Livello III', 'emails' => ['f.degennaro@qualificagroup.it']],
        'felice.salomone@qualificagroup.com' => ['roles' => ['Web Master / SEO Specialist'], 'level' => 'Livello II', 'emails' => ['web@qualificagroup.it']],
        'ferdinando.flagiello@qualificagroup.com' => ['roles' => ['Responsabile Finanza Agevolata', 'Responsabile Istruzione', 'Responsabile Progettazione'], 'level' => 'Livello I', 'emails' => ['f.flagiello@qualificagroup.it', 'istruzione@qualificagroup.it']],
        'fernando.annunziata@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior', 'Responsabile Sicurezza'], 'level' => 'Livello I', 'emails' => ['sicurezza@qualificagroup.it', 'retecommerciale@qualificagroup.it']],
        'flavio.scognamiglio@qualificagroup.com' => ['roles' => ['Tecnico Sistemi e Supporto Utenti'], 'level' => 'Livello III', 'emails' => ['f.scognamiglio@qualificagroup.it']],
        'francesca.derrico@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['f.derrico@qualificagroup.it']],
        'francesca.forgione@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['benevento@qualificagroup.it']],
        'francesca.mauriello@qualificagroup.com' => ['roles' => ['Supporto Operativo SOA'], 'level' => 'Livello III', 'emails' => ['f.mauriello@qualificagroup.it']],
        'francesca.russodelprete@qualificagroup.com' => ['roles' => ['Responsabile GDPR/Privacy', 'Responsabile ISO'], 'level' => 'Livello I', 'emails' => ['ufficioqualita@qualificagroup.it']],
        'francesco.dellacorte@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['casaldiprincipe@qualificagroup.it']],
        'francesco.dimaio@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'level' => 'Livello III', 'emails' => ['sicurezza5@qualificagroup.it']],
        'francesco.ienco@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => ['francesco.consulenza@qualificagroup.it']],
        'gaetano.dellaporta@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['g.dellaporta@qualificagroup.it']],
        'gaetano.farina@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'level' => 'Livello III', 'emails' => ['g.farina@qualificagroup.it', 'sicurezza1@qualificagroup.it']],
        'gessica.crispo@qualificagroup.com' => ['roles' => ['Supervisor Operatori Politiche Attive'], 'level' => 'Livello II', 'emails' => ['g.crispo@qualificagroup.it']],
        'giada.curzola@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['pomezia@qualificagroup.it']],
        'giovanna.gervasio@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive', 'Addetto Ricerca e Selezione'], 'level' => 'Livello IV', 'emails' => ['g.gervasio@qualificagroup.it', 'cv@qualificagroup.it']],
        'giulia.costanzo@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['assistenzacommerciale@qualificagroup.it']],
        'giuseppe.agus@qualificagroup.com' => ['roles' => ['Referente Normativo', 'Addetto Progettazione'], 'level' => 'Livello II', 'emails' => ['altaformazione@qualificagroup.it']],
        'giuseppe.gallo@qualificagroup.com' => ['roles' => ['Supervisor Commerciale Sedi Territoriali'], 'level' => 'Livello II', 'emails' => ['g.gallo@qualificagroup.it']],
        'giuseppe.tudisco@qualificagroup.com' => ['roles' => ['Referente Legale'], 'level' => 'Livello II', 'emails' => ['avv.tudisco@qualificagroup.it']],
        'grazia.triola@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'level' => 'Livello III', 'emails' => ['backoffice.aversa@qualificagroup.it']],
        'gresianovella.cannizzo@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['catania2@qualificagroup.it']],
        'ida.panico@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative'], 'level' => 'Livello II', 'emails' => ['gol@qualificagroup.it']],
        'ilaria.striano@qualificagroup.com' => ['roles' => ['Coordinatore Segreteria Didattica'], 'level' => 'Livello II', 'emails' => ['segreteriadidattica@qualificagroup.it']],
        'ilaria.vitolo@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => ['i.vitolo@qualificagroup.it']],
        'ivana.mango@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => []],
        'jessica.chentrens@qualificagroup.com' => ['roles' => ['Responsabile Formazione'], 'level' => 'Livello I', 'emails' => ['direzionedidattica@qualificagroup.it', 'formazione@qualificagroup.it']],
        'jessica.faettini@qualificagroup.com' => ['roles' => ['Referente Territoriale Politiche Attive'], 'level' => 'Livello II', 'emails' => ['j.faettini@qualificagroup.it']],
        'laura.allegretta@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative'], 'level' => 'Livello II', 'emails' => ['l.allegretta@qualificagroup.it']],
        'lea.pellegrino@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['l.pellegrino@qualificagroup.it']],
        'lia.palladino@qualificagroup.com' => ['roles' => ['Back Office Commerciale'], 'level' => 'Livello III', 'emails' => ['l.palladino@qualificagroup.it']],
        'lidia.russo@qualificagroup.com' => ['roles' => ['Supervisor Back Office Commerciale'], 'level' => 'Livello II', 'emails' => ['backoffice@qualificagroup.it', 'offerte@qualificagroup.it']],
        'lillo.irrera@qualificagroup.com' => ['roles' => ['Responsabile Sistemi e Supporto Utenti'], 'level' => 'Livello I', 'emails' => ['it@qualificagroup.it']],
        'luana.logozzo@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['bergamo3@qualificagroup.it']],
        'luca.ponticelli@qualificagroup.com' => ['roles' => ['Responsabile Gestione Sedi', 'Responsabile Servizi Generali', 'Responsabile UPPCG'], 'level' => 'Livello I', 'emails' => ['l.ponticelli@qualificagroup.it']],
        'luca.romano@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['casalnuovo@qualificagroup.it']],
        'luigifabio.pescosolido@qualificagroup.com' => ['roles' => ['Operativo Ufficio Accreditamenti', 'Operativo Gestione Sedi', 'Supporto Operativo UPPCG'], 'level' => 'Livello II', 'emails' => ['luigi.fabio@qualificagroup.it', 'rendicontazione.sedi@qualificagroup.it']],
        'manuela.rivolta@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'level' => 'Livello III', 'emails' => ['aplmilano@qualificagroup.it']],
        'marco.baldi@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['customer@qualificagroup.it']],
        'marco.fedele@qualificagroup.com' => ['roles' => ['Consulente Commerciale'], 'level' => 'Livello III', 'emails' => ['m.fedele@qualificagroup.it']],
        'maria.bellini@qualificagroup.com' => ['roles' => ['Addetto Contabilità Fornitori e IVA'], 'level' => 'Livello III', 'emails' => ['m.bellini@qualificagroup.it']],
        'maria.dallara@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['palermo@qualificagroup.it']],
        'maria.iannotta@qualificagroup.com' => ['roles' => ['Responsabile Accreditamenti', 'Responsabile Gare', 'Responsabile SOA'], 'level' => 'Livello I', 'emails' => ['soa@qualificagroup.it']],
        'maria.pezzella@qualificagroup.com' => ['roles' => ['Travel Assistant'], 'level' => 'Livello III', 'emails' => ['m.pezzella@qualificagroup.it']],
        'maria.savarese@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => ['m.savarese@qualificagroup.it']],
        'mariaclelia.bernardi@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['casilina2@qualificagroup.it']],
        'mariarita.balzamo@qualificagroup.com' => ['roles' => ['Supporto Operativo Bandi'], 'level' => 'Livello III', 'emails' => ['m.balzamo@qualificagroup.it']],
        'marilisa.taglialatela@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['giugliano@qualificagroup.it']],
        'mario.esposito@qualificagroup.com' => ['roles' => ['Consulente ISO', 'Responsabile Qualità'], 'level' => 'Livello I', 'emails' => ['m.esposito@qualificagroup.it']],
        'marlena.jaruga@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative', 'Coordinatore Segreteria Didattica'], 'level' => 'Livello II', 'emails' => ['roma@qualificagroup.it']],
        'marta.maggio@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['nettuno@qualificagroup.it']],
        'martina.dimarco@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['viterbo2@qualificagroup.it']],
        'martina.mosca@qualificagroup.com' => ['roles' => ['Supervisor Back Office Commerciale'], 'level' => 'Livello II', 'emails' => ['assistenza@qualificagroup.it']],
        'michela.fabozzi@qualificagroup.com' => ['roles' => ['Responsabile Commerciale'], 'level' => 'Livello I', 'emails' => ['asscommerciale@qualificagroup.it', 'retecommerciale@qualificagroup.it']],
        'michela.poti@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['milano2@qualificagroup.it']],
        'michele.vitale@qualificagroup.com' => ['roles' => ['Coordinatore Segreteria Didattica'], 'level' => 'Livello II', 'emails' => ['percorsifinanziati@qualificagroup.it']],
        'miriam.dellaversana@qualificagroup.com' => ['roles' => ['Social Media Manager Junior'], 'level' => 'Livello III', 'emails' => ['social3@qualificagroup.it']],
        'nicola.eliseo@qualificagroup.com' => ['roles' => ['Sviluppatore Software'], 'level' => 'Livello III', 'emails' => []],
        'nicoletta.esposito@qualificagroup.com' => ['roles' => ['Supervisor Attività Formative', 'Coordinatore Segreteria Didattica'], 'level' => 'Livello II', 'emails' => ['n.esposito@qualificagroup.it']],
        'nicolo.caruso@qualificagroup.com' => ['roles' => ['Sviluppatore Software'], 'level' => 'Livello III', 'emails' => []],
        'noemi.nigliato@qualificagroup.it' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => []],
        'nunzio.guida@qualificagroup.com' => ['roles' => ['Direttore Tecnico – Responsabile Unit Travel'], 'level' => 'Livello I', 'emails' => ['n.guida@qualificagroup.it']],
        'paola.sena@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => ['paola.consulenza@qualificagroup.it']],
        'pasquale.orsusanno@qualificagroup.com' => ['roles' => ['Tecnico Sicurezza'], 'level' => 'Livello III', 'emails' => ['sicurezza3@qualificagroup.it']],
        'patricia.christovao@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['basilicata@qualificagroup.it']],
        'pina.derosa@qualificagroup.com' => ['roles' => ['Tecnico Gare'], 'level' => 'Livello III', 'emails' => ['gare@qualificagroup.it', 'ufficiogare@qualificagroup.it', 'avvalimenti@qualificagroup.it']],
        'raffaele.centore@qualificagroup.it' => ['roles' => ['Commercialista Esterno'], 'level' => 'Livello I', 'emails' => []],
        'raffaele.distico@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive'], 'level' => 'Livello IV', 'emails' => ['r.distico@qualificagroup.it']],
        'raffaele.nocerino@qualificagroup.com' => ['roles' => ['Consulente ISO'], 'level' => 'Livello III', 'emails' => ['raffaele.consulenza@qualificagroup.it']],
        'rita.carrese@qualificagroup.com' => ['roles' => ['Supporto Operativo Bandi', 'Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['aversa@qualificagroup.it']],
        'roberta.apperti@qualificagroup.com' => ['roles' => ['Responsabile Fondi Interprofessionali'], 'level' => 'Livello I', 'emails' => ['r.apperti@qualificagroup.it']],
        'roberto.rubino@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['r.rubino@qualificagroup.it']],
        'roberto.sepe@qualificagroup.com' => ['roles' => ['Tecnico SOA'], 'level' => 'Livello III', 'emails' => ['soa2@qualificagroup.it']],
        'rosa.falzarano@qualificagroup.com' => ['roles' => ['Supervisor Commerciale Sedi Territoriali'], 'level' => 'Livello II', 'emails' => ['commercialegol@qualificagroup.it']],
        'rosa.pagliara@qualificagroup.com' => ['roles' => ['Addetto Progettazione', 'Consulente Istruzione'], 'level' => 'Livello III', 'emails' => ['r.pagliara@qualificagroup.it']],
        'sabino.figurelli@qualificagroup.com' => ['roles' => ['Social Media Manager Junior'], 'level' => 'Livello III', 'emails' => ['social2@qualificagroup.it']],
        'samantha.castagna@qualificagroup.com' => ['roles' => ['Operatore Politiche Attive', 'Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['bergamo@qualificagroup.it']],
        'sarasilvana.cavallo@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['gela@qualificagroup.it']],
        'simona.chiacchio@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['convenzionigol@qualificagroup.it']],
        'simone.seneca@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['s.seneca@qualificagroup.it']],
        'stefania.silvestro@qualificagroup.com' => ['roles' => ['Addetto Segreteria'], 'level' => 'Livello II', 'emails' => ['segreteria@qualificagroup.it', 'acquisti@qualificagroup.it']],
        'tiziana.digesu@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['gela@qualificagroup.it']],
        'umberto.casiello@qualificagroup.com' => ['roles' => ['CFO', 'Supporto Operativo Amministrazione'], 'level' => 'Livello I', 'emails' => ['umberto.casiello@qualificagroup.it']],
        'umberto.santamaria@qualificagroup.com' => ['roles' => ['Responsabile Marketing'], 'level' => 'Livello I', 'emails' => ['social@qualificagroup.it']],
        'valentina.cavaliere@qualificagroup.com' => ['roles' => ['Addetto Segreteria'], 'level' => 'Livello III', 'emails' => ['segreteria2@qualificagroup.it']],
        'valentina.scala@qualificagroup.com' => ['roles' => ['Consulente Commerciale Junior'], 'level' => 'Livello IV', 'emails' => ['collaborazioni@qualificagroup.it']],
        'valentina.sorrentino@qualificagroup.com' => ['roles' => ['HR Generalist'], 'level' => 'Livello III', 'emails' => ['v.sorrentino@qualificagroup.it', 'cv@qualificagroup.it']],
        'vincenzo.crisci@qualificagroup.com' => ['roles' => ['Consulente Commerciale Senior'], 'level' => 'Livello III', 'emails' => ['v.crisci@qualificagroup.it']],
        'yailin.calderon@qualificagroup.com' => ['roles' => ['Segreteria Didattica'], 'level' => 'Livello III', 'emails' => ['grumellodelmonte@qualificagroup.it']],
    ];
}
