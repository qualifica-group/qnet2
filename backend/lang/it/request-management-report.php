<?php

return [

    // GA2 cell labels (spec 0106, AC-005/AC-007): "TOTALE" is the branch
    // aggregate row, "Non assegnato" the requests with no GA2 Operatore.
    'labels' => [
        'total' => 'TOTALE',
        'unassigned' => 'Non assegnato',
    ],

    // The CSV header cells, in the contract's fixed order (data_contract,
    // AC-004): translated server-side, in the run's frozen locale.
    'headers' => [
        'category' => 'Categoria',
        'ga2' => 'GA2',
        'telefonate' => 'N. Telefonate Effettuate',
        // Spec 0159: range-free columns; their range-bound twins below say so.
        'unhandled_callbacks' => 'N. Richiami non gestiti',
        'unhandled_new_contacts' => 'N. Nuovi contatti non gestiti',
        'current_potentials' => 'N. Potenziali associati',
        'richiami' => 'N. Richiami non gestiti (nel periodo selezionato)',
        'nuovi_contatti' => 'N. Nuovi contatti non gestiti (nel periodo selezionato)',
        'potenziali' => 'N. Potenziali associati (nel periodo selezionato)',
        'aule_gestione' => 'Aule in gestione',
        'aule_partenza' => 'Aule in partenza',
        'associati' => 'Associati',
        'aziende_inserite' => 'Aziende inserite',
        'presa_appuntamenti' => 'Presa Appuntamenti',
        'trattative_concluse' => 'Trattative Concluse',
        'invio_presa_in_carico' => 'Invio Presa in carico',
    ],

];
