<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Request Management CSV report — indicator columns (spec 0106; D-2-bis
    | of spec 0107)
    |--------------------------------------------------------------------------
    |
    | The eleven indicator columns, in the CONTRACT order — the single neutral
    | source both the CALCULATION layer (ReportBranchRowsBuilder, which fills
    | every one of them, 0 default per D-15) and every FORMATTING/consumer
    | layer (ReportCsvBuilder, RequestManagementDashboardBuilder) read from.
    | Neither depends on the other for this list any more (spec 0107 D-2-bis,
    | point 3): a calculation class must never depend on a formatting class
    | to know what it is calculating.
    |
    */

    'indicator_columns' => [
        'telefonate', 'richiami', 'nuovi_contatti', 'potenziali',
        'aule_gestione', 'aule_partenza', 'associati', 'aziende_inserite',
        'presa_appuntamenti', 'trattative_concluse', 'invio_presa_in_carico',
    ],

    /*
    |--------------------------------------------------------------------------
    | Active columns per reportable category (user directive 2026-09-18)
    |--------------------------------------------------------------------------
    |
    | Keyed by the category NAME, matched case-insensitively (the branches
    | are dynamic, spec 0131, but these are the business' own categories).
    | A column absent from a category's list is 0 for that category, in the
    | dashboard AND in the file; a reportable category absent from this map
    | has every column at 0.
    |
    */

    'category_columns' => [
        'gol' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'autoimpiego' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'yisu' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'dil' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'autofinanziato' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_partenza', 'associati'],
        'consulenza' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aziende_inserite', 'presa_appuntamenti', 'trattative_concluse'],
        'apl' => ['telefonate', 'richiami', 'nuovi_contatti', 'invio_presa_in_carico'],
    ],

];
