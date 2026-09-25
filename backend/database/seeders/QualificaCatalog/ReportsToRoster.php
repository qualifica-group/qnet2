<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Who each operator reports to ("Risponde a", spec 0166), transcribed from
 * "Mansionario Operatori_Abilitazioni 1 (Supervisione).csv" (user directive
 * 2026-09-25). Pure data, read by QualificaReportsToSeeder.
 *
 * Keyed by email, like OperatorRoster, so a name spelled differently in the
 * CSV ("Jessica Faiettini" for Faettini) still lands on the right account.
 * An empty list means "reports to no one" and is synced as such.
 *
 * Deliberately left out: the CSV rows whose account does not exist — the ones
 * OperatorRoster already excludes (Miriam Del Giudice, Maddalena Vitale,
 * Elisa Finizio, Imma Pascale) and Miriam Cantatore, who is in neither roster.
 */
final class ReportsToRoster
{
    private const string COORDINATOR = 'michela.fabozzi@qualificagroup.com';

    private const string MARKETING_MANAGER = 'umberto.santamaria@qualificagroup.com';

    private const array COMMERCIAL_SUPERVISORS = ['rosa.falzarano@qualificagroup.com', 'fabrizio.aliberti@qualificagroup.com'];

    /**
     * operator email => the emails of their managers.
     *
     * @var array<string, list<string>>
     */
    public const array MANAGERS = [
        self::COORDINATOR => [],
        'rosa.falzarano@qualificagroup.com' => [self::COORDINATOR],
        'fabrizio.aliberti@qualificagroup.com' => [self::COORDINATOR],
        self::MARKETING_MANAGER => [self::COORDINATOR],
        'simona.chiacchio@qualificagroup.com' => [...self::COMMERCIAL_SUPERVISORS, self::MARKETING_MANAGER],
        'sabino.figurelli@qualificagroup.com' => [self::MARKETING_MANAGER],
        'gaetano.dellaporta@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'marco.fedele@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'valentina.scala@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'simone.seneca@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'anna.garofalo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'giulia.costanzo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'lea.pellegrino@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'vincenzo.crisci@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'francesca.forgione@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'marco.baldi@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'biagio.fusco@qualificagroup.it' => self::COMMERCIAL_SUPERVISORS,
        'antonio.alvoni@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'fernando.annunziata@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'jessica.faettini@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'yailin.calderon@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'samantha.castagna@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'cristina.leotta@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'luana.logozzo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'michela.poti@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'elhajiri.zhour@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'manuela.rivolta@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'constantin.popa@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'desire.romito@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'martina.dimarco@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'marlena.jaruga@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'anastasia.marcacci@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'mariaclelia.bernardi@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'silvia.avorio@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'silvia.paternesi@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'alessandra.mentella@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'francesca.derrico@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'tania.macale@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'aurora.piccinato@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'michela.fanti@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'marta.maggio@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'giada.curzola@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'giulia.dangelo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'alessandra.gaspari@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'simona.curi@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'chiara.centracchio@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'daila.lobartolo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'alessia.margiotta@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'laura.clessidra@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'valentina.guarino@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'eva.spataro@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'mariaconcetta.muscia@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'tiziana.digesu@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'sarasilvana.cavallo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'jessica.virgolini@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'daniela.petringa@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'gresianovella.cannizzo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'martina.scognamiglio@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'luca.romano@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'anna.palumbo@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'stefania.dandolfi@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'francesco.crispino@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'andreana.giuliano@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'elena.derosa@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'francesco.dellacorte@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'francesco.darbitrio@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'marilisa.taglialatela@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
        'sara.armerini@qualificagroup.com' => self::COMMERCIAL_SUPERVISORS,
    ];
}
