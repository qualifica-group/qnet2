<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue as Roles;

/**
 * The client's operator roster, transcribed from "Mansionario Operatori
 * Abilitazioni aggiornato (2).xlsx" (user directive 2026-09-15). Pure data,
 * read by QualificaOperatorSeeder.
 *
 * Normalised at transcription, not at runtime:
 *  - names in Title Case, stray tabs and double spaces removed, split into
 *    first and last name by hand (compound names like "Maria Clelia" or
 *    "Paternesi Meloni" cannot be split mechanically);
 *  - Sedi as CITY names: the seeder expands each one to EVERY operational site
 *    whose alias is that city ("Frattamaggiore" = "FRATTAMAGGIORE 1 (HQ)" and
 *    "Frattamaggiore 2"); "Roma Casilina" is the "Roma" city;
 *  - categories as catalogue names (QualificaCatalogSeeder::CATALOG): every
 *    "APL <regione>" is the single "APL" branch, which has no regional split.
 *
 * Deliberately left out (user decisions 2026-09-15):
 *  - the rows highlighted in yellow (Miriam Del Giudice, Maddalena Vitale,
 *    Elisa Finizio, Imma Pascale): those accounts do not exist;
 *  - the "Consulenza" category: it is not assigned as a competence to anyone,
 *    nor linked to a business function to make it assignable;
 *  - "Roma (partner Galletti)", which names no operational site.
 *
 * The CSV's "no operatore" profiles carry NO enabled city and NO category
 * (user decision 2026-09-15): they must never be assignable, so they keep
 * their physical Sede only and no competence row — spec 0111 rev.2 D-9 reads
 * that as competent for nothing. Their unrestricted role already sees every
 * record without a remote membership.
 */
final class OperatorRoster
{
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
     * first name, last name, email, job (the sheet's "Settore"), role, physical
     * city, enabled cities, product categories.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: array<int, string>, 7: array<int, string>}>
     */
    public const array OPERATORS = [
        ['Michela', 'Fabozzi', 'michela.fabozzi@qualificagroup.com', 'Coordinatore Commerciale', Roles::COORDINATOR_ROLE, 'Frattamaggiore', [], []],
        ['Rosa', 'Falzarano', 'rosa.falzarano@qualificagroup.com', 'Supervisor Commerciale', Roles::SUPERVISOR_ROLE, 'Frattamaggiore', [], []],
        ['Fabrizio', 'Aliberti', 'fabrizio.aliberti@qualificagroup.com', 'Supervisor Commerciale', Roles::SUPERVISOR_ROLE, 'Frattamaggiore', [], []],
        ['Umberto', 'Santamaria', 'umberto.santamaria@qualificagroup.com', 'Responsabile Marketing', Roles::COORDINATOR_ROLE, 'Frattamaggiore', [], []],
        ['Simona', 'Chiacchio', 'simona.chiacchio@qualificagroup.com', 'Commerciale - Supporto Marketing', Roles::COORDINATOR_ROLE, 'Frattamaggiore', [], []],
        ['Sabino', 'Figurelli', 'sabino.figurelli@qualificagroup.com', 'Supporto Marketing', Roles::MARKETING_ROLE, 'Frattamaggiore', [], []],
        ['Gaetano', 'Della Porta', 'gaetano.dellaporta@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Marco', 'Fedele', 'marco.fedele@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Valentina', 'Scala', 'valentina.scala@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Simone', 'Seneca', 'simone.seneca@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Anna', 'Garofalo', 'anna.garofalo@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], self::CAMPANIA_HUB_CATEGORIES],
        ['Giulia', 'Costanzo', 'giulia.costanzo@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Lea', 'Pellegrino', 'lea.pellegrino@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Vincenzo', 'Crisci', 'vincenzo.crisci@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Frattamaggiore', self::FRATTAMAGGIORE_HUB, self::CAMPANIA_HUB_CATEGORIES],
        ['Francesca', 'Forgione', 'francesca.forgione@qualificagroup.com', 'Commerciale', Roles::ENROLLEE_COMMERCIAL_ROLE, 'Benevento', ['Frattamaggiore'], self::CAMPANIA_CATEGORIES],
        ['Marco', 'Baldi', 'marco.baldi@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], []],
        ['Biagio', 'Fusco', 'biagio.fusco@qualificagroup.it', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], []],
        ['Antonio', 'Alvoni', 'antonio.alvoni@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], ['Autoimpiego']],
        ['Fernando', 'Annunziata', 'fernando.annunziata@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Frattamaggiore', ['Frattamaggiore'], []],
        ['Jessica', 'Faettini', 'jessica.faettini@qualificagroup.com', 'Supervisor APL', Roles::COMMERCIAL_ROLE, 'Grumello del Monte', ['Grumello del Monte'], self::LOMBARDY_APL_CATEGORIES],
        ['Yadin', 'De Pina Calderon', 'yailin.calderon@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Grumello del Monte', ['Grumello del Monte'], self::LOMBARDY_CATEGORIES],
        ['Samantha Egle', 'Castagna', 'samantha.castagna@qualificagroup.com', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Bergamo', ['Bergamo'], self::LOMBARDY_APL_CATEGORIES],
        ['Cristina', 'Leotta', 'cristina.leotta@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Bergamo', ['Bergamo'], self::LOMBARDY_CATEGORIES],
        ['Luana', 'Logozzo', 'luana.logozzo@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Bergamo', ['Bergamo'], self::LOMBARDY_CATEGORIES],
        ['Michela', 'Potì', 'michela.poti@qualificagroup.com', 'Formazione', Roles::COMMERCIAL_ROLE, 'Milano', ['Milano'], self::LOMBARDY_CATEGORIES],
        ['Zhour', 'El Hajiri', 'elhajiri.zhour@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Bologna', ['Milano'], self::LOMBARDY_CATEGORIES],
        ['Manuela', 'Rivolta', 'manuela.rivolta@qualificagroup.com', 'APL', Roles::COMMERCIAL_ROLE, 'Milano', ['Milano'], self::LOMBARDY_APL_CATEGORIES],
        ['Constantin', 'Popa', 'constantin.popa@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Roma', ['Roma'], []],
        ['Desirè', 'Romito', 'desire.romito@qualificagroup.com', 'APL - Commerciale', Roles::COMMERCIAL_ROLE, 'Viterbo', ['Viterbo'], self::LAZIO_APL_CATEGORIES],
        ['Martina', 'Di Marco', 'martina.dimarco@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Viterbo', ['Viterbo'], self::LAZIO_CATEGORIES],
        ['Marlena', 'Jaruga', 'marlena.jaruga@qualificagroup.com', 'Supervisor Didattica', Roles::TEACHING_SUPERVISOR_ROLE, 'Roma', ['Roma', 'Viterbo', 'Fonte Nuova', 'Cassino', 'Latina', 'Nettuno', 'Pomezia', 'Gaeta'], self::LAZIO_CATEGORIES],
        ['Anastasia', 'Marcacci', 'anastasia.marcacci@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Roma', ['Roma'], self::LAZIO_CATEGORIES],
        ['Maria Clelia', 'Bernardi', 'mariaclelia.bernardi@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Roma', ['Roma'], self::LAZIO_CATEGORIES],
        ['Silvia', 'Avorio', 'silvia.avorio@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Fonte Nuova', ['Fonte Nuova'], self::LAZIO_CATEGORIES],
        ['Silvia', 'Paternesi Meloni', 'silvia.paternesi@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Fonte Nuova', ['Fonte Nuova'], self::LAZIO_CATEGORIES],
        ['Alessandra', 'Mentella', 'alessandra.mentella@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Cassino', ['Cassino'], self::LAZIO_CATEGORIES],
        ['Francesca', "D'Errico", 'francesca.derrico@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Cassino', ['Cassino'], self::LAZIO_CATEGORIES],
        ['Tania', 'Macale', 'tania.macale@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Latina', ['Latina'], self::LAZIO_CATEGORIES],
        ['Aurora', 'Piccinato', 'aurora.piccinato@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Latina', ['Latina'], self::LAZIO_CATEGORIES],
        ['Michela', 'Fanti', 'michela.fanti@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Latina', ['Latina'], self::LAZIO_CATEGORIES],
        ['Marta', 'Maggio', 'marta.maggio@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Nettuno', ['Nettuno'], self::LAZIO_CATEGORIES],
        ['Giada', 'Curzola', 'giada.curzola@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Pomezia', ['Pomezia'], self::LAZIO_CATEGORIES],
        ['Giulia', "D'Angelo", 'giulia.dangelo@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Pescara', ['Pescara'], ['GOL - Abruzzo', 'Autoimpiego', 'Autofinanziato']],
        ['Alessandra', 'Gaspari', 'alessandra.gaspari@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Pescara', ['Pescara'], ['GOL - Abruzzo', 'Autoimpiego', 'Autofinanziato']],
        ['Simona', 'Curi', 'simona.curi@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Terni', ['Terni'], ['GOL - Umbria', 'Autoimpiego', 'Autofinanziato']],
        ['Chiara', 'Centracchio', 'chiara.centracchio@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Isernia', ['Isernia'], ['GOL - Molise', 'Autoimpiego', 'Autofinanziato']],
        ['Daila', 'Lo Bartolo', 'daila.lobartolo@qualificagroup.com', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Mazzarino', self::SICILY_HUB, self::SICILY_APL_CATEGORIES],
        ['Alessia', 'Margiotta', 'alessia.margiotta@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Mazzarino', self::SICILY_HUB, self::SICILY_APL_CATEGORIES],
        ['Laura', 'Clessidra', 'laura.clessidra@qualificagroup.com', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Palermo', ['Palermo'], self::SICILY_APL_CATEGORIES],
        ['Valentina', 'Guarino', 'valentina.guarino@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Comiso', ['Comiso'], self::SICILY_CATEGORIES],
        ['Eva', 'Spataro', 'eva.spataro@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Comiso', ['Comiso'], self::SICILY_CATEGORIES],
        ['Maria Concetta', 'Muscia', 'mariaconcetta.muscia@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Niscemi', ['Niscemi'], self::SICILY_CATEGORIES],
        ['Tiziana', 'Di Gesù', 'tiziana.digesu@qualificagroup.com', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Gela', ['Gela'], self::SICILY_APL_CATEGORIES],
        ['Sara', 'Cavallo', 'sarasilvana.cavallo@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Gela', ['Gela'], self::SICILY_CATEGORIES],
        ['Daniela Agata', 'Petringa', 'daniela.petringa@qualificagroup.com', 'APL - Commerciale - Formazione', Roles::COMMERCIAL_ROLE, 'Catania', ['Catania'], self::SICILY_APL_CATEGORIES],
        ['Gresia', 'Cannizzo', 'gresianovella.cannizzo@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Catania', ['Catania'], self::SICILY_APL_CATEGORIES],
        ['Martina', 'Scognamiglio', 'martina.scognamiglio@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Luca', 'Romano', 'luca.romano@qualificagroup.com', 'Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Anna', 'Palumbo', 'anna.palumbo@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Stefania', "D'Andolfi", 'stefania.dandolfi@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Francesco', 'Crispino', 'francesco.crispino@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casalnuovo di Napoli', ['Casalnuovo di Napoli'], self::CAMPANIA_CATEGORIES],
        ['Andreana', 'Giuliano', 'andreana.giuliano@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Nola', ['Nola'], self::CAMPANIA_CATEGORIES],
        ['Elena', 'De Rosa', 'elena.derosa@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Cardito', ['Frattamaggiore 1', 'Cardito', 'Aversa', 'Caserta', 'Nola', 'Teverola', 'Benevento 1', 'Frattamaggiore 2'], self::CAMPANIA_CATEGORIES],
        ['Francesco', 'Della Corte', 'francesco.dellacorte@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Casal di Principe', ['Casal di Principe'], self::CAMPANIA_CATEGORIES],
        ['Francesco', "D'Arbitrio", 'francesco.darbitrio@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Villaricca', ['Villaricca'], self::CAMPANIA_CATEGORIES],
        ['Marilisa', 'Scafati Taglialatela', 'marilisa.taglialatela@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Giugliano in Campania', ['Giugliano in Campania'], self::CAMPANIA_CATEGORIES],
        ['Sara', 'Armerini', 'sara.armerini@qualificagroup.com', 'Formazione - Commerciale', Roles::COMMERCIAL_ROLE, 'Avellino', ['Avellino'], self::CAMPANIA_CATEGORIES],
    ];
}
