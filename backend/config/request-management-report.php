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
    | Request Management CSV report — branches (spec 0106)
    |--------------------------------------------------------------------------
    |
    | The six report branches, in the FIXED order the CSV rows follow. Each
    | branch is identified by NAME, bound by identity to
    | Database\Seeders\QualificaCatalogSeeder::CATALOG (never a hard-coded
    | id): App\Services\RequestManagement\Report\ReportBranchResolver looks
    | the root category up by name (+ its parent's name, when it has one) at
    | request time, then expands it to its full subtree via
    | CategoryHierarchy::descendantIds(). `columns` is the applicability
    | allow-list (spec 0106 data_contract): it only decides WHICH indicators
    | are computed for the branch (skips querying the ones that make no
    | sense for it) — the CSV cell itself is always numeric, `0` for a
    | column NOT listed here, same as any other unmeasured value (D-15,
    | rev-2, overrides the former D-9 empty-cell distinction).
    |
    */

    'branches' => [

        'gol' => [
            'category' => ['parent' => 'Formazione', 'name' => 'GOL'],
            'columns' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        ],

        'autoimpiego' => [
            'category' => ['parent' => 'Formazione', 'name' => 'Autoimpiego'],
            'columns' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        ],

        'yisu' => [
            'category' => ['parent' => 'Formazione', 'name' => 'Yisu'],
            'columns' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        ],

        'autofinanziato' => [
            'category' => ['parent' => 'Formazione', 'name' => 'Autofinanziato'],
            'columns' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_partenza', 'associati'],
        ],

        'consulenza' => [
            'category' => ['parent' => null, 'name' => 'Consulenza'],
            'columns' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aziende_inserite', 'presa_appuntamenti', 'trattative_concluse'],
        ],

        'apl' => [
            'category' => ['parent' => null, 'name' => 'APL'],
            'columns' => ['telefonate', 'richiami', 'nuovi_contatti', 'invio_presa_in_carico'],
        ],

    ],

];
