<?php

return [
    'company_site_mismatch' => 'La sede selezionata non appartiene alla società selezionata.',
    'layout_module_mismatch' => 'Il layout selezionato non appartiene al modulo Preventivi.',
    'layout_inactive' => 'Il layout selezionato è disattivato.',
    'no_layout_available' => 'Nessun layout disponibile per generare il documento: crea o attiva un layout predefinito per i Preventivi.',
    'offer_line_required' => "L'offerta deve contenere almeno una riga prodotto.",
    'offer_line_required_for_status' => "Non puoi portare l'offerta a questo stato senza almeno una riga prodotto.",
    // Spec 0142, D-5: keyed by App\Enums\ProductUsage value.
    'product_not_usable' => [
        'SALE' => 'Il prodotto selezionato non è vendibile.',
        'COST' => 'Il prodotto selezionato non è utilizzabile come costo.',
    ],
    // Spec 0144, D-4/D-5: imputazione di una riga costo a una riga prodotto.
    'cost_line_offer_line_id_invalid' => 'La riga prodotto selezionata non appartiene a questa offerta.',
    'cost_line_offer_line_index_invalid' => 'La riga prodotto selezionata non è tra quelle inviate.',
];
