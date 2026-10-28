<?php

return [
    'delete_has_accounting_documents' => 'La commessa non può essere eliminata: ha documenti contabili collegati (richieste proforma, fatture, ordini di acquisto o fatture passive).',
    'lines_not_offer_revenue' => 'Le righe scelte devono essere righe di ricavo delle offerte dei contratti selezionati.',
    'line_already_programmed' => 'Una delle righe scelte è già nella commessa :code: ogni riga può stare in una sola commessa.',
    'multi_quote' => [
        'contracts_not_won' => 'Questi contratti non sono in uno stato "Chiuso positivo" e non si possono ancora programmare: :codes. Toglili dalla selezione.',
        'contracts_not_allowed' => 'Questi contratti non si possono cumulare in una commessa multi-offerta: :codes. La loro offerta non ha righe di un ramo con l\'impostazione "Commessa multi-offerta" attiva: toglili dalla selezione.',
        'lines_not_allowed' => 'Ogni riga scelta deve avere un prodotto di un ramo con l\'impostazione "Commessa multi-offerta" attiva.',
        'lines_different_branches' => 'Le righe scelte appartengono a rami diversi: una commessa multi-offerta raccoglie righe di un solo ramo.',
        'lines_single_quote' => 'Le righe scelte vengono da una sola offerta: una commessa multi-offerta richiede righe di almeno due offerte. Per una sola offerta usa Programma dal contratto.',
        'lines_removal_only' => 'In una commessa multi-offerta puoi solo togliere righe, lasciandone almeno una: non puoi aggiungerne di nuove.',
    ],
    'progress' => [
        'percentage_not_above_current' => 'La percentuale deve essere maggiore dell\'avanzamento attuale (:current%).',
        'recorded_on_before_latest' => 'La data non può essere anteriore all\'ultima registrazione (:date).',
        'work_order_closed' => 'La commessa è chiusa: riaprila per aggiornare l\'avanzamento.',
    ],
];
