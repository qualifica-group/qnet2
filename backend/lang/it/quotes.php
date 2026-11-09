<?php

return [
    'company_site_mismatch' => 'La sede selezionata non appartiene alla società selezionata.',
    'layout_module_mismatch' => 'Il layout selezionato non appartiene al modulo Preventivi.',
    'layout_inactive' => 'Il layout selezionato è disattivato.',
    'no_layout_available' => 'Nessun layout disponibile per generare il documento: crea o attiva un layout predefinito per i Preventivi.',
    'offer_line_required' => "L'offerta deve contenere almeno una riga prodotto.",
    'offer_line_required_for_status' => "Non puoi portare l'offerta a questo stato senza almeno una riga prodotto.",
    'offer_line_required_to_validate_contract' => "Non puoi validare il contratto senza almeno una riga prodotto nell'offerta.",
    // Spec 0360, D-4: a REVENUE row assigned to a work order is frozen.
    'line_removal_blocked_by_work_order' => 'La riga :product è assegnata alla commessa :code: elimina prima la commessa.',
    'line_locked_by_work_order' => 'La riga è assegnata alla commessa :code e non si può modificare.',
    // Spec 0142, D-5: keyed by App\Enums\ProductUsage value.
    'product_not_usable' => [
        'SALE' => 'Il prodotto selezionato non è vendibile.',
        'COST' => 'Il prodotto selezionato non è utilizzabile come costo.',
    ],
    // Spec 0144, D-4/D-5: imputazione di una riga costo a una riga prodotto.
    'cost_line_offer_line_id_invalid' => 'La riga prodotto selezionata non appartiene a questa offerta.',
    'cost_line_offer_line_index_invalid' => 'La riga prodotto selezionata non è tra quelle inviate.',
    // Spec 0308, D-3.
    'commission_recalculation' => [
        'closed' => "Le commissioni di un'offerta chiusa non si ricalcolano.",
    ],
    // Spec 0320, D-5.
    'supplier_required' => 'Il fornitore è obbligatorio per questo prodotto.',
    'supplier_not_of_product' => 'Il fornitore selezionato non è tra i fornitori del prodotto.',
];
