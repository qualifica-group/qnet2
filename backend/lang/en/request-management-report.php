<?php

return [

    // GA2 cell labels (spec 0106, AC-005/AC-007): "TOTAL" is the branch
    // aggregate row, "Unassigned" the requests with no GA2 Operatore.
    'labels' => [
        'total' => 'TOTAL',
        'unassigned' => 'Unassigned',
    ],

    // The CSV header cells, in the contract's fixed order (data_contract,
    // AC-004): translated server-side, in the run's frozen locale.
    'headers' => [
        'category' => 'Category',
        'ga2' => 'GA2',
        'telefonate' => 'Calls Made',
        // Spec 0159: range-free columns; their range-bound twins below say so.
        'unhandled_callbacks' => 'Unhandled Callbacks',
        'unhandled_new_contacts' => 'Unhandled New Contacts',
        'current_potentials' => 'Potential Leads',
        'richiami' => 'Unhandled Callbacks (selected period)',
        'nuovi_contatti' => 'Unhandled New Contacts (selected period)',
        'potenziali' => 'Potential Leads (selected period)',
        'aule_gestione' => 'Classes In Progress',
        'aule_partenza' => 'Classes Starting',
        'associati' => 'Enrolled',
        'aziende_inserite' => 'Companies Added',
        'presa_appuntamenti' => 'Appointments Booked',
        'trattative_concluse' => 'Deals Closed',
        'invio_presa_in_carico' => 'Handover Sent',
    ],

];
