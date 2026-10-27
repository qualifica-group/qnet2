<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Who held which QNet legacy role (snapshot of 2026-10-27): the ACTIVE legacy
 * accounts whose active qnet-2 account held no role but the base one, keyed by
 * the qnet-2 email. The people the mansionario and the office rosters already
 * give a role are absent: their roles were decided there. A row whose legacy
 * roles all map to nothing is absent too. Pure data, read by
 * QualificaLegacyRoleSeeder through LegacyRoleCatalogue::LEGACY_ROLES.
 */
final class LegacyRoleRoster
{
    /**
     * qnet-2 email => the legacy role names, verbatim.
     *
     * @var array<string, array<int, string>>
     */
    public const array USERS = [
        // Andrea Arena
        'andrea.arena@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Anna Di Maio
        'anna.dimaio@qualificagroup.com' => ['Admin', 'COP_ Coordinamento Operativo e Pianificazione', 'FOR_Segreteria Didattica', 'SEG_Segreteria', 'Ufficio Acquisti'],
        // Anna Venere
        'anna.venere@qualificagroup.com' => ['Amministrazione', 'CON _ Contabilità'],
        // Annalisa Giordano
        'annalisa.giordano@qualificagroup.com' => ['FOR_Segreteria Didattica', 'Partner formazione gol'],
        // Annarita Palumbo
        'annarita.palumbo@qualificagroup.com' => ['Admin', 'COORDINATORI DI SETTORE E RISORSE', 'COP_ Coordinamento Operativo e Pianificazione', 'PIA _ Pianificazione'],
        // Antonino Fasone
        'antonino.fasone@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Antonio De Martino
        'antonio.demartino@qualificagroup.com' => ['LAV _ Tecnico Gdpr'],
        // Antonio Foglia
        'antonio.foglia@qualificagroup.com' => ['LAV - R _ FON - Responsabile Tecnico Fondi Interprofessionali', 'LAV - R _ ISTR - Responsabile Istruzione'],
        // Antonio Salvatore Peluso
        'antoniosalvatore.peluso@qualificagroup.com' => ['LAV _ Tecnico Gdpr'],
        // Bernadetta Romano
        'bernadetta.romano@qualificagroup.com' => ['CON _ Contabilità'],
        // Claudia Di Natale
        'claudia.dinatale@qualificagroup.com' => ['COM_Settore Commerciale', 'FOR_Segreteria Didattica', 'LAV_ Tecnico APL- PAL'],
        // Claudia Testa
        'claudia.testa@qualificagroup.com' => ['COM_Social Media Manager'],
        // Consiglia Cosentino
        'consiglia.cosentino@qualificagroup.com' => ['Admin', 'COP_ Coordinamento Operativo e Pianificazione'],
        // Dario D'Aleo
        'dario.daleo@qualificagroup.com' => ['COM_Internal Sales Consultant'],
        // Edvige Graziano
        'edvige.graziano@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Elio Rettori
        'elio.rettori@qualificagroup.com' => ['LAV - R _ Responsabile Tecnico Settore Sicurezza', 'LAV _ Tecnico Sicurezza'],
        // Emilianna Credentino
        'emilianna.credentino@qualificagroup.com' => ['Partner formazione gol'],
        // Federica De Gennaro
        'federica.degennaro@qualificagroup.com' => ['LAV_ Tecnico Finanza Agevolata'],
        // Ferdinando Flagiello
        'ferdinando.flagiello@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'LAV_ Tecnico Finanza Agevolata'],
        // Francesca Mauriello
        'francesca.mauriello@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Francesca Russo Del Prete
        'francesca.russodelprete@qualificagroup.com' => ['Admin', 'COORDINATORI DI SETTORE E RISORSE', 'COP_ Coordinamento Operativo e Pianificazione', 'PIA _ Pianificazione', 'Registro NC'],
        // Francesco Di Maio
        'francesco.dimaio@qualificagroup.com' => ['LAV - R _ Responsabile Tecnico Settore Sicurezza', 'LAV _ Tecnico Sicurezza', 'LAV _ Tecnico della Sicurezza sul Lavoro'],
        // Francesco Ienco
        'francesco.ienco@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Gaetano Farina
        'gaetano.farina@qualificagroup.com' => ['LAV - R _ Responsabile Tecnico Settore Sicurezza', 'LAV _ Tecnico Sicurezza'],
        // Giuseppe Agus
        'giuseppe.agus@qualificagroup.com' => ['LAV - R _ ISTR - Responsabile Istruzione', 'LAV_ Tecnico PR Sedi'],
        // Giuseppe Gallo
        'giuseppe.gallo@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Giuseppe Tudisco
        'giuseppe.tudisco@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Grazia Triola
        'grazia.triola@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'FOR_Segreteria Didattica'],
        // Graziella La Piana
        'graziella.lapiana@qualificagroup.com' => ['COM_Settore Commerciale', 'FOR_Segreteria Didattica', 'LAV_ Tecnico APL- PAL'],
        // Ida Panico
        'ida.panico@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Ilaria Striano
        'ilaria.striano@qualificagroup.com' => ['Admin', 'COORDINATORI DI SETTORE E RISORSE', 'COP_ Coordinamento Operativo e Pianificazione', 'FOR_Segreteria Didattica', 'PIA _ Pianificazione', 'Pianificazione Speciale'],
        // Ilaria Vitolo
        'ilaria.vitolo@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Ivana Mango
        'ivana.mango@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Jessica Chentrens
        'jessica.chentrens@qualificagroup.com' => ['Admin'],
        // Jessica Virgolini
        'jessica.virgolini@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'LAV_ Tecnico APL- PAL'],
        // Laura Allegretta
        'laura.allegretta@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Lillo Irrera
        'lillo.irrera@qualificagroup.com' => ['Admin', 'Gestione Ticket'],
        // Luca Ponticelli
        'luca.ponticelli@qualificagroup.com' => ['Amministrazione', 'CON _ Contabilità', 'COP_ Coordinamento Operativo e Pianificazione', 'Ufficio Acquisti', 'Ufficio Acquisti (Autorizzazione)', 'Vedi tutti i segnatempo'],
        // Luigi Ciotola
        'luigi.ciotola@qualificagroup.com' => ['COM_Internal Sales Consultant', 'FOR_Segreteria Didattica'],
        // Luigi Cirillo
        'luigi.cirillo@qualificagroup.com' => ['COM_Internal Sales Consultant'],
        // Luigi Fabio Pescosolido
        'luigifabio.pescosolido@qualificagroup.com' => ['Amministrazione', 'CON _ Contabilità', 'COORDINATORI DI SETTORE E RISORSE', 'COP_ Coordinamento Operativo e Pianificazione', 'Ufficio Acquisti'],
        // Maria Bellini
        'maria.bellini@qualificagroup.com' => ['Amministrazione', 'CON _ Contabilità'],
        // Maria Dallara
        'maria.dallara@qualificagroup.com' => ['COM_Settore Commerciale', 'FOR_Segreteria Didattica', 'LAV_ Tecnico APL- PAL'],
        // Maria Iannotta
        'maria.iannotta@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'Registro NC'],
        // Maria Pezzella
        'maria.pezzella@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Maria Savarese
        'maria.savarese@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Mariarita Balzamo
        'mariarita.balzamo@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Marzio Martone
        'marzio.martone@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Michele Vitale
        'michele.vitale@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'FOR_Segreteria Didattica', 'PIA _ Pianificazione', 'Pianificazione Speciale'],
        // Miriam Dell'Aversana
        'miriam.dellaversana@qualificagroup.com' => ['COM_Social Media Manager'],
        // Nicoletta Esposito
        'nicoletta.esposito@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'Gestione Ticket', 'PIA _ Pianificazione', 'Pianificazione Speciale'],
        // Nicolò Caruso
        'nicolo.caruso@qualificagroup.com' => ['Admin'],
        // Noemi Nigliato
        'noemi.nigliato@qualificagroup.it' => ['Admin', 'Gestione Ticket', 'Upload Manuale Operativo'],
        // Nunzio Guida
        'nunzio.guida@qualificagroup.com' => ['LAV - R _ ISTR - Responsabile Istruzione'],
        // Olimpia Di Domenico
        'olimpia.didomenico@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Paola Sena
        'paola.sena@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Paola Volpe
        'paola.volpe@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Pasquale Orsusanno
        'pasquale.orsusanno@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione'],
        // Patricia Christovao
        'patricia.christovao@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Pina De Rosa
        'pina.derosa@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'LAV_Coordinatore Reparto Gare_Avvalimenti'],
        // Raffaele Nocerino
        'raffaele.nocerino@qualificagroup.com' => ['LAV _ Tecnico Iso'],
        // Rita Carrese
        'rita.carrese@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'Pianificazione Speciale'],
        // Roberto Rubino
        'roberto.rubino@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Roberto Sepe
        'roberto.sepe@qualificagroup.com' => ['LAV _ Tecnico SOA Gare'],
        // Romilda Critelli
        'romilda.critelli@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Sabrina Palumbo
        'sabrina.palumbo@qualificagroup.com' => ['FOR_Segreteria Didattica'],
        // Silvia Amoruso
        'silvia.amoruso@qualificagroup.com' => ['COM_Settore Commerciale'],
        // Silvia Meccariello
        'silvia.meccariello@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'LAV_ Tecnico APL- PAL'],
        // Stefania Silvestro
        'stefania.silvestro@qualificagroup.com' => ['COP_ Coordinamento Operativo e Pianificazione', 'SEG_Segreteria'],
        // Umberto Casiello
        'umberto.casiello@qualificagroup.com' => ['Amministrazione', 'CFO', 'CON _ Contabilità', 'COP_ Coordinamento Operativo e Pianificazione'],
        // Valentina Cavaliere
        'valentina.cavaliere@qualificagroup.com' => ['Amministrazione', 'CON _ Contabilità', 'COP_ Coordinamento Operativo e Pianificazione', 'SEG_Segreteria', 'Ufficio Acquisti'],
    ];
}
