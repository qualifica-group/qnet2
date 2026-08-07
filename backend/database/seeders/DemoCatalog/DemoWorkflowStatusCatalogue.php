<?php

namespace Database\Seeders\DemoCatalog;

/**
 * The DEMO "stati di lavorazione" (spec 0047): one working-state pick list per
 * BRANCH of DemoCategoryCatalogue, so a demo request can be walked from its
 * first contact to its closure. Pure data — DemoCategoryWorkflowSeeder holds
 * the logic and binds one workflow per category of the branch (the criterion
 * is `product_category_id`, matched on the exact category of a product line,
 * never an ancestor).
 *
 * `CUSTOM` are the intermediate rows — the `validated` one included, since
 * that is a plain group and not a system key (user directive 2026-08-07);
 * `PINNED` seeds the 3 rows WorkflowStatusWriter always creates
 * (open/closed_won/closed_lost) with labels that read like the branch,
 * instead of the writer's generic ones. `color` is a badge token
 * (BADGE_COLOR_TOKENS), never a raw hex.
 */
final class DemoWorkflowStatusCatalogue
{
    /**
     * The CriterionFieldRegistry allow-list key every demo workflow matches on.
     */
    public const string CRITERION_FIELD = 'product_category_id';

    /**
     * Branch root => intermediate statuses, in pick-list order.
     *
     * @var array<string, list<array{name: string, description: string, color: string, group: string, requires_note: bool}>>
     */
    public const array CUSTOM = [
        'Servizi Formativi' => [
            [
                'name' => 'Contattato',
                'description' => 'Primo contatto effettuato: interesse raccolto, iscrizione non ancora formalizzata.',
                'color' => 'blue',
                'group' => 'pending',
                'requires_note' => false,
            ],
            [
                'name' => 'In attesa documenti',
                'description' => 'Iscrizione sospesa in attesa dei documenti richiesti al partecipante.',
                'color' => 'amber',
                'group' => 'pending',
                'requires_note' => true,
            ],
            [
                'name' => 'Documenti verificati',
                'description' => 'Documentazione ricevuta e controllata: si puo\' procedere con l\'iscrizione.',
                'color' => 'teal',
                'group' => 'pending',
                'requires_note' => false,
            ],
            [
                'name' => 'Iscritto',
                'description' => 'Partecipante iscritto all\'edizione, in attesa dell\'avvio del corso.',
                'color' => 'violet',
                'group' => 'pending',
                'requires_note' => false,
            ],
            [
                'name' => 'Iscrizione confermata',
                'description' => 'Iscrizione verificata dalla segreteria, in attesa dell\'avvio.',
                'color' => 'violet',
                'group' => 'validated',
                'requires_note' => false,
            ],
        ],
        'Consulenza Aziendale' => [
            [
                'name' => 'Appuntamento fissato',
                'description' => 'Incontro concordato con il cliente per l\'analisi dell\'esigenza.',
                'color' => 'indigo',
                'group' => 'pending',
                'requires_note' => false,
            ],
            [
                'name' => 'Analisi in corso',
                'description' => 'Raccolta dei requisiti e stima delle giornate di intervento.',
                'color' => 'blue',
                'group' => 'pending',
                'requires_note' => false,
            ],
            [
                'name' => 'Offerta inviata',
                'description' => 'Proposta economica trasmessa al cliente: si attende riscontro.',
                'color' => 'amber',
                'group' => 'pending',
                'requires_note' => true,
            ],
            [
                'name' => 'Offerta accettata',
                'description' => 'Proposta accettata dal cliente, in attesa dell\'avvio dell\'incarico.',
                'color' => 'violet',
                'group' => 'validated',
                'requires_note' => false,
            ],
        ],
    ];

    /**
     * Branch root => the descriptive seed of each pinned system row, keyed by
     * `system_key`. Every branch fills all three: a set with a gap would fall
     * back to a generic label in the middle of a domain-specific pick list.
     *
     * @var array<string, array<string, array{name: string, description: string, color: ?string, requires_note: bool}>>
     */
    public const array PINNED = [
        'Servizi Formativi' => [
            'open' => [
                'name' => 'Da contattare',
                'description' => 'Richiesta acquisita: nessun contatto ancora effettuato.',
                'color' => null,
                'requires_note' => false,
            ],
            'closed_won' => [
                'name' => 'Corso avviato',
                'description' => 'Partecipante in aula: la lavorazione si chiude con esito positivo.',
                'color' => null,
                'requires_note' => false,
            ],
            'closed_lost' => [
                'name' => 'Rinuncia',
                'description' => 'Il partecipante ha rinunciato: la richiesta non prosegue.',
                'color' => null,
                'requires_note' => true,
            ],
        ],
        'Consulenza Aziendale' => [
            'open' => [
                'name' => 'Da qualificare',
                'description' => 'Richiesta acquisita: esigenza non ancora qualificata.',
                'color' => null,
                'requires_note' => false,
            ],
            'closed_won' => [
                'name' => 'Incarico avviato',
                'description' => 'Incarico firmato e avviato: la lavorazione si chiude con esito positivo.',
                'color' => null,
                'requires_note' => false,
            ],
            'closed_lost' => [
                'name' => 'Non interessato',
                'description' => 'Il cliente non prosegue: la richiesta si chiude senza esito.',
                'color' => null,
                'requires_note' => true,
            ],
        ],
    ];
}
