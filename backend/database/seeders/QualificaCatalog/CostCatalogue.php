<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's cost-only products (user directive 2026-10-16): the legacy
 * cost items — expense reports, warehouse articles, vehicles, equipment —
 * transcribed as NEW qnet products, active, under their own "Costi" branch,
 * usable only on an Offerta's Costi tab. Pure data — QualificaCostProductSeeder
 * holds the logic.
 *
 * A separate catalogue from the legacy import's "[OLD] Costi" branch (user
 * decision): these rows carry no `old_id`, so the `cost-products` import still
 * files its own history copies and the migrated cost lines keep pointing at
 * those. The employee levels (`user_levels`) are left out by user request, and
 * so are the legacy test rows ("Alpha", "oust", "Motosega"). Names are cleaned
 * up (no "FOR_" prefix), amounts are the legacy ones.
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
            ['name' => 'Pagamento fornitore', 'cost' => 0.0, 'plus_vat' => false, 'description' => null],
            ['name' => 'Docenti formazione', 'cost' => 20.0, 'plus_vat' => false, 'description' => 'Costo dei docenti della formazione'],
            ['name' => "Commissione d'esame", 'cost' => 400.0, 'plus_vat' => false, 'description' => null],
            ['name' => 'Piattaforma formazione', 'cost' => 150.0, 'plus_vat' => false, 'description' => null],
            ['name' => 'Contributo discenti', 'cost' => 1.0, 'plus_vat' => false, 'description' => 'Contributo da versare per singolo discente'],
            ['name' => 'Commissioni partner formazione', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
        ],
        'Articoli' => [
            ['name' => 'Biglietti aerei', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Biglietti treno', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Alloggi', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
            ['name' => 'Noleggio auto', 'cost' => 0.0, 'plus_vat' => true, 'description' => null],
        ],
        'Veicoli' => [
            [
                'name' => 'Tagliando Smart (FE700FV)',
                'cost' => 140.0,
                'plus_vat' => true,
                'description' => 'Smart Enrico - Tagliando completo con cambio olio, filtro olio, filtro aria e filtro carburante',
            ],
            [
                'name' => 'Escavatore idraulico 18ZT',
                'cost' => 200.0,
                'plus_vat' => true,
                'description' => "Denominazione del costruttore: SAMPIERANA spa - EUROCOMACH\n"
                    ."Denominazione commerciale: 18ZT\n"
                    ."Tipologia di macchina: ESCAVATORE IDRAULICO - MACCHINA OPERATRICE\n"
                    ."Numero di matricola: SMP1E122KNLE00110\n"
                    .'Anno di fabbricazione: 2022',
            ],
            [
                'name' => 'Piattaforma verticale elettrica 2632iE',
                'cost' => 140.0,
                'plus_vat' => true,
                'description' => "Denominazione del costruttore: HELI ITALIA SRL\n"
                    ."Denominazione commerciale: 2632iE\n"
                    ."Tipologia di macchina: PIATTAFORMA VERTICALE ELETTRICA\n"
                    ."Numero di matricola: M11000533\n"
                    .'Anno di fabbricazione: 2022',
            ],
        ],
        'Attrezzature' => [
            ['name' => 'Pompa derattizzante', 'cost' => 300.0, 'plus_vat' => true, 'description' => null],
        ],
    ];
}
