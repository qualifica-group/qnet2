<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue as Roles;

/**
 * The client's operator roster, transcribed from "Mansionario
 * Operatori_Abilitazioni.csv" (user directive 2026-09-15). Pure data, read by
 * QualificaOperatorSeeder.
 *
 * Normalised at transcription, not at runtime:
 *  - names in Title Case, stray tabs and double spaces removed;
 *  - Sedi as CITY names: the seeder expands each one to EVERY operational site
 *    whose alias is that city ("Frattamaggiore" = "FRATTAMAGGIORE 1 (HQ)" and
 *    "Frattamaggiore 2"); "Roma Casilina" is the "Roma" city;
 *  - categories as catalogue names (QualificaCatalogSeeder::CATALOG): every
 *    "APL <regione>" is the single "APL" branch, which has no regional split.
 *
 * Deliberately left out (user decisions 2026-09-15):
 *  - the three rows with no email (Linda - Latina, Miriam Cantatore - Nettuno,
 *    Jessica - Riesi): the email is the account's natural key;
 *  - the "Consulenza" category: it is not assigned as a competence to anyone,
 *    nor linked to a business function to make it assignable;
 *  - "Roma (partner Galletti)", which names no operational site.
 *
 * `sites` / `categories` set to ALL mean "Tutte": the profile covers every
 * product category (spec 0129 D-1) and keeps its physical Sede only — its
 * unrestricted role already sees every record, so no remote membership is
 * needed (and none would pull it into the per-site lead distribution).
 */
final class OperatorRoster
{
    public const string ALL = '*';

    private const array FRATTAMAGGIORE_HUB = ['Cassino', 'Roma', 'Milano', 'Frattamaggiore'];

    private const array SICILY_HUB = ['Mazzarino', 'Riesi', 'Gela', 'Catania'];

    private const array CAMPANIA_HUB_CATEGORIES = ['GOL - Campania', 'GOL - Lazio', 'GOL - Lombardia', 'Autofinanziato', 'Autoimpiego', 'APL'];

    private const array CAMPANIA_CATEGORIES = ['GOL - Campania', 'Autofinanziato', 'Autoimpiego', 'APL'];

    private const array LOMBARDY_APL_CATEGORIES = ['GOL - Lombardia', 'Autoimpiego', 'APL', 'DIL'];

    private const array LOMBARDY_CATEGORIES = ['GOL - Lombardia', 'Autoimpiego', 'DIL'];

    private const array LAZIO_APL_CATEGORIES = ['GOL - Lazio', 'Autoimpiego', 'Autofinanziato', 'APL'];

    private const array LAZIO_CATEGORIES = ['GOL - Lazio', 'Autoimpiego', 'Autofinanziato'];

    private const array SICILY_APL_CATEGORIES = ['APL', 'Autoimpiego', 'Autofinanziato', 'GOL - Sicilia'];

    private const array SICILY_CATEGORIES = ['Autoimpiego', 'Autofinanziato', 'GOL - Sicilia'];

    /**
     * name, email, job (the CSV "Settore"), role, physical city, enabled cities
     * (or ALL), product categories (or ALL).
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string|array<int, string>, 6: string|array<int, string>}>
     */
    public const array OPERATORS = [
        ['Michela Fabozzi', 'commerciale@qualificagroup.it', 'Coordinatore Commerciale', Roles::COORDINATOR_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Rosa Falzarano', 'commercialegol@qualificagroup.it', 'Supervisor Commerciale', Roles::SUPERVISOR_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Fabrizio Aliberti', 'supportocommerciale@qualificagroup.it', 'Supervisor Commerciale', Roles::SUPERVISOR_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Umberto Santamaria', 'social@qualificagroup.it', 'Responsabile Marketing', Roles::COORDINATOR_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Simona Chiacchio', 'convenzionigol@qualificagroup.it', 'Commerciale - Supporto Marketing', Roles::COORDINATOR_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Sabino Figurelli', 'social2@qualificagroup.it', 'Supporto Marketing', Roles::MARKETING_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Miriam Del Giudice', 'social3@qualificagroup.it', 'Supporto Marketing', Roles::MARKETING_ROLE, 'Frattamaggiore', self::ALL, self::ALL],
        ['Gaetano Della Porta', 'g.dellaporta@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Marco Fedele', 'm.fedele@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Valentina Scala', 'v.v@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Simone Seneca', 's.seneca@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Anna Garofalo', 'commerciale2@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], self::CAMPANIA_HUB_CATEGORIES],
        ['Giulia Costanzo', 'assistenzacommerciale@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Lea Pellegrino', 'l.pellegrino@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Vincenzo Crisci', 'v.crisci@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Francesca Forgione', 'benevento@qualificagroup.it', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Benevento', ['Frattamaggiore'], self::CAMPANIA_CATEGORIES],
        ['Marco Baldi', 'customer@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], []],
        ['Biagio Fusco', 'biagio.fusco@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], []],
        ['Antonio Alvoni', 'a.alvoni@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], ['Autoimpiego']],
        ['Fernando Annunziata', 'sicurezza@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], []],
        ['Jessica Faettini', 'j.faettini@qualificagroup.it', 'Supervisor APL', Roles::COMMERCIAL_ROLE, 'Grumello del Monte', ['Grumello del Monte'], self::LOMBARDY_APL_CATEGORIES],
        ['Yadin De Pina Calderon', 'grumellodelmonte@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Grumello del Monte', ['Grumello del Monte'], self::LOMBARDY_CATEGORIES],
        ['Samantha Egle Castagna', 'bergamo@qualificagroup.it', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Bergamo', ['Bergamo'], self::LOMBARDY_APL_CATEGORIES],
        ['Cristina Leotta', 'bergamo2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Bergamo', ['Bergamo'], self::LOMBARDY_CATEGORIES],
        ['Luana Logozzo', 'bergamo3@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Bergamo', ['Bergamo'], self::LOMBARDY_CATEGORIES],
        ['Michela Potì', 'milano@qualificagroup.it', 'Formazione', Roles::COMMERCIAL_ROLE, 'Milano', ['Milano'], self::LOMBARDY_CATEGORIES],
        ['Zhour El Hajiri', 'bologna@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Bologna', ['Milano'], self::LOMBARDY_CATEGORIES],
        ['Manuela Rivolta', 'pero@qualificagroup.it', 'APL', Roles::COMMERCIAL_ROLE, 'Milano', ['Milano'], self::LOMBARDY_APL_CATEGORIES],
        ['Constantin Popa', 'c.popa@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Roma', ['Roma'], []],
        ['Desirè Romito', 'viterbo@qualificagroup.it', 'APL - Commerciale', Roles::COMMERCIAL_ROLE, 'Viterbo', ['Viterbo'], self::LAZIO_APL_CATEGORIES],
        ['Martina Di Marco', 'viterbo2@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Viterbo', ['Viterbo'], self::LAZIO_CATEGORIES],
        ['Marlena Jaruga', 'roma@qualificagroup.it', 'Supervisor Didattica', Roles::TEACHING_SUPERVISOR_ROLE, 'Roma', ['Roma', 'Viterbo', 'Fonte Nuova', 'Cassino', 'Latina', 'Nettuno', 'Pomezia', 'Gaeta'], self::LAZIO_CATEGORIES],
        ['Anastasia Marcacci', 'casilina@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Roma', ['Roma'], self::LAZIO_CATEGORIES],
        ['Maria Clelia Bernardi', 'casilina2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Roma', ['Roma'], self::LAZIO_CATEGORIES],
        ['Silvia Avorio', 'fontenuova@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Fonte Nuova', ['Fonte Nuova'], self::LAZIO_CATEGORIES],
        ['Silvia Paternesi Meloni', 'fontenuova2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Fonte Nuova', ['Fonte Nuova'], self::LAZIO_CATEGORIES],
        ['Alessandra Mentella', 'cassino2@kronosformazione.eu', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Cassino', ['Cassino'], self::LAZIO_CATEGORIES],
        ["Francesca D'Errico", 'cassino@kronosformazione.eu', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Cassino', ['Cassino'], self::LAZIO_CATEGORIES],
        ['Tania Macale', 'latina@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Latina', ['Latina'], self::LAZIO_CATEGORIES],
        ['Aurora Piccinato', 'latina2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Latina', ['Latina'], self::LAZIO_CATEGORIES],
        ['Michela Fanti', 'latina3@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Latina', ['Latina'], self::LAZIO_CATEGORIES],
        ['Marta Maggio', 'nettuno@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Nettuno', ['Nettuno'], self::LAZIO_CATEGORIES],
        ['Giada Curzola', 'pomezia@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Pomezia', ['Pomezia'], self::LAZIO_CATEGORIES],
        ['Maddalena Vitale', 'problemsolving.gaeta@gmail.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Gaeta', ['Gaeta'], self::LAZIO_CATEGORIES],
        ["Giulia D'Angelo", 'pescara@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Pescara', ['Pescara'], ['GOL - Abruzzo', 'Autoimpiego', 'Autofinanziato']],
        ['Alessandra Gaspari', 'pescara2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Pescara', ['Pescara'], ['GOL - Abruzzo', 'Autoimpiego', 'Autofinanziato']],
        ['Simona Curi', 'terni@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Terni', ['Terni'], ['GOL - Umbria', 'Autoimpiego', 'Autofinanziato']],
        ['Chiara Centracchio', 'isernia@kronosformazione.eu', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Isernia', ['Isernia'], ['GOL - Molise', 'Autoimpiego', 'Autofinanziato']],
        ['Daila Lo Bartolo', 'gol.mazzarino@qualificagroup.it', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Mazzarino', self::SICILY_HUB, self::SICILY_APL_CATEGORIES],
        ['Alessia Margiotta', 'a.margiotta@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Mazzarino', self::SICILY_HUB, self::SICILY_APL_CATEGORIES],
        ['Laura Clessidra', 'palermo@qualificagroup.it', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Palermo', ['Palermo'], self::SICILY_APL_CATEGORIES],
        ['Valentina Guarino', 'v.guarino@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Comiso', ['Comiso'], self::SICILY_CATEGORIES],
        ['Eva Spataro', 'comiso@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Comiso', ['Comiso'], self::SICILY_CATEGORIES],
        ['Maria Concetta Muscia', 'mc.muscia@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Niscemi', ['Niscemi'], self::SICILY_CATEGORIES],
        ['Tiziana Di Gesù', 'gela2@qualificagroup.it', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Gela', ['Gela'], self::SICILY_APL_CATEGORIES],
        ['Sara Cavallo', 'gela@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Gela', ['Gela'], self::SICILY_CATEGORIES],
        ['Daniela Agata Petringa', 'catania@qualificagroup.it', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Catania', ['Catania'], self::SICILY_APL_CATEGORIES],
        ['Gresia Cannizzo', 'catania2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Catania', ['Catania'], self::SICILY_APL_CATEGORIES],
        ['Martina Scognamiglio', 'amministrazionecasalnuovo@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Luca Romano', 'casalnuovo@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Anna Palumbo', 'casalnuovo1@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ["Stefania D'Andolfi", 'casalnuovo2@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Francesco Crispino', 'tutorcasalnuovo@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Andreana Giuliano', 'nolagol@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Nola', ['Nola'], self::CAMPANIA_CATEGORIES],
        ['Elena De Rosa', 'cardito@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Cardito', ['Frattamaggiore 1', 'Cardito', 'Aversa', 'Caserta', 'Nola', 'Teverola', 'Benevento 1', 'Frattamaggiore 2'], self::CAMPANIA_CATEGORIES],
        ['Francesco Della Corte', 'casaldiprincipe@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casal di Principe', ['Casal di Principe'], self::CAMPANIA_CATEGORIES],
        ["Francesco D'Arbitrio", 'villaricca@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Villaricca', ['Villaricca'], self::CAMPANIA_CATEGORIES],
        ['Marilisa Scafati Taglialatela', 'giugliano@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Giugliano in Campania', ['Giugliano in Campania'], self::CAMPANIA_CATEGORIES],
        ['Sara Armerini', 'edp@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Avellino', ['Avellino'], self::CAMPANIA_CATEGORIES],
        ['Elisa Finizio', 'edp1@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Avellino', ['Avellino'], self::CAMPANIA_CATEGORIES],
        ['Imma Pascale', 'castellamare@qualificagroup.it', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Castellammare di Stabia', ['Castellammare di Stabia'], self::CAMPANIA_CATEGORIES],
    ];
}
