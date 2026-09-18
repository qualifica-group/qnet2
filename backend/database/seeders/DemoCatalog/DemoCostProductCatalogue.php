<?php

namespace Database\Seeders\DemoCatalog;

/**
 * The DEMO cost-only catalogue (spec 0142): expense items an Offerta books on
 * its Costi tab — travel, lodging, materials — never sold, so every row is
 * `Usable as cost` only. Pure data — DemoCostProductSeeder holds the logic.
 *
 * The branch root deliberately carries NO business function: without one a
 * category never pairs into a product line (PicksDemoOffers,
 * ResolvesCategoryBusinessFunction skip it), so these items can never become
 * an opportunity's "prodotti di interesse" or a REVENUE row.
 *
 * `unit` is a `units_of_measure.code` from UnitOfMeasureSeeder; `cost` is a
 * plain demo figure, and `price` mirrors it (a cost item has no margin).
 */
final class DemoCostProductCatalogue
{
    public const string ROOT = 'Spese e Trasferte';

    /**
     * Leaf category name => its cost items.
     *
     * @var array<string, list<array{name: string, cost: float, unit: string}>>
     */
    public const array PRODUCTS = [
        'Trasporti' => [
            ['name' => 'Noleggio auto', 'cost' => 65.0, 'unit' => 'day'],
            ['name' => 'Carburante', 'cost' => 1.85, 'unit' => 'litre'],
            ['name' => 'Biglietto treno Alta Velocita', 'cost' => 89.0, 'unit' => 'unit'],
            ['name' => 'Biglietto treno Regionale', 'cost' => 12.5, 'unit' => 'unit'],
            ['name' => 'Biglietto aereo', 'cost' => 180.0, 'unit' => 'unit'],
            ['name' => 'Taxi', 'cost' => 30.0, 'unit' => 'unit'],
            ['name' => 'Pedaggio autostradale', 'cost' => 18.0, 'unit' => 'unit'],
            ['name' => 'Parcheggio', 'cost' => 15.0, 'unit' => 'day'],
        ],
        'Vitto e Alloggio' => [
            ['name' => 'Pernottamento hotel', 'cost' => 110.0, 'unit' => 'day'],
            ['name' => 'Pasto', 'cost' => 25.0, 'unit' => 'unit'],
        ],
        'Materiali e Servizi Esterni' => [
            ['name' => 'Materiale didattico', 'cost' => 20.0, 'unit' => 'unit'],
            ['name' => 'Affitto aula', 'cost' => 250.0, 'unit' => 'day'],
            ['name' => 'Docente esterno', 'cost' => 60.0, 'unit' => 'hour'],
        ],
    ];
}
