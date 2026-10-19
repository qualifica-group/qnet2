<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's cost-only products (user directive 2026-10-16): the legacy
 * cost items — expense reports and warehouse articles — transcribed as NEW
 * qnet products, active, under their own "Costi" branch, usable only on an
 * Offerta's Costi tab. Pure data — QualificaCostProductSeeder holds the logic.
 *
 * A separate catalogue from the legacy import's "[OLD] Costi" branch (user
 * decision): these rows carry no `old_id`, so the `cost-products` import still
 * files its own history copies and the migrated cost lines keep pointing at
 * those. Left out by user request: the employee levels (`user_levels`), the
 * vehicles and equipment, three expense reports ("Pagamento Fornitore",
 * "FOR_Discenti", "FOR_Commissioni_Partner") and the legacy test rows
 * ("Alpha", "oust"). Names are cleaned up (no "FOR_" prefix), amounts are the
 * legacy ones.
 *
 * The root carries NO business function: without one a category never pairs
 * into a product line, so a cost item never becomes a REVENUE row (same choice
 * as DemoCostProductCatalogue). `cost` doubles as `price`: a cost item has no
 * margin. `plus_vat` = the legacy row is at 22%; the others carry no rate.
 */
final class CostCatalogue
{
    public const string ROOT = 'Costi';

    /**
     * Leaf category name => its cost items.
     *
     * @var array<string, list<array{name: string, cost: float, plus_vat: bool, description: string|null}>>
     */
    public const array PRODUCTS = [
        'Note spese' => [
            ['name' => 'Pedaggi', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Spese di cancelleria', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Spese varie', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Docenti formazione', 'cost' => 20.0, 'plus_vat' => false, 'description' => 'Costo dei docenti della formazione'],
            ['name' => "Commissione d'esame", 'cost' => 400.0, 'plus_vat' => false, 'description' => null],
            ['name' => 'Piattaforma formazione', 'cost' => 150.0, 'plus_vat' => false, 'description' => null],
        ],
        'Articoli' => [
            ['name' => 'Biglietti aerei', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Biglietti treno', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Alloggi', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Noleggio auto', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
        ],
    ];
}
