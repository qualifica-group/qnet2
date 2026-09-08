<?php

return [

    // GA2 cell labels (spec 0106, AC-005/AC-007): "TOTAL" is the branch
    // aggregate row, "Unassigned" the requests with no GA2 Operatore.
    'labels' => [
        'total' => 'TOTAL',
        'unassigned' => 'Unassigned',
    ],

    // The 13 CSV header cells, in the contract's fixed order (data_contract,
    // AC-004): translated server-side, in the run's frozen locale.
    'headers' => [
        'category' => 'Category',
        'ga2' => 'GA2',
        'telefonate' => 'Calls Made',
        'richiami' => 'Unhandled Callbacks',
        'nuovi_contatti' => 'Unhandled New Contacts',
        'potenziali' => 'Potential Leads',
        'aule_gestione' => 'Classes In Progress',
        'aule_partenza' => 'Classes Starting',
        'associati' => 'Enrolled',
        'aziende_inserite' => 'Companies Added',
        'presa_appuntamenti' => 'Appointments Booked',
        'trattative_concluse' => 'Deals Closed',
        'invio_presa_in_carico' => 'Handover Sent',
    ],

];
