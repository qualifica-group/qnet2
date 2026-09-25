<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Request Management CSV report — indicator columns (spec 0106; D-2-bis
    | of spec 0107)
    |--------------------------------------------------------------------------
    |
    | The indicator columns, in the CONTRACT order — the single neutral
    | source both the CALCULATION layer (ReportBranchRowsBuilder, which fills
    | every one of them, 0 default per D-15) and every FORMATTING/consumer
    | layer (ReportCsvBuilder, RequestManagementDashboardBuilder) read from.
    | Neither depends on the other for this list any more (spec 0107 D-2-bis,
    | point 3): a calculation class must never depend on a formatting class
    | to know what it is calculating.
    |
    */

    // Spec 0159: the three range-free columns sit where their range-bound
    // twins (richiami, nuovi_contatti, potenziali) used to, which they
    // replace in the production seed; the twins follow them.
    'indicator_columns' => [
        'telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials',
        'richiami', 'nuovi_contatti', 'potenziali',
        'aule_gestione', 'aule_partenza', 'associati', 'aziende_inserite',
        'presa_appuntamenti', 'trattative_concluse', 'invio_presa_in_carico',
    ],

    // The per-category column selection (formerly `category_columns`, keyed
    // by category NAME) is retired by spec 0141: it now lives in
    // `product_categories.report_columns` (DB), resolved read-side by
    // App\Services\ProductCategories\ReportColumnsInheritance. The snapshot
    // this map used to hold is frozen, once, inside migration
    // `2026_09_18_110000_add_report_columns_to_product_categories_table`.

];
