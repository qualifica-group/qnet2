<?php

return [
    'company_locked' => 'La società di un ordine di acquisto non può essere cambiata dopo la creazione.',
    'not_editable' => 'Questo ordine di acquisto non può più essere modificato.',
    'linked_to_purchase_invoice' => 'Questo ordine di acquisto è collegato a una fattura passiva.',
    'not_sendable_status' => 'Solo gli ordini di acquisto approvati o già inviati possono essere inviati al fornitore.',
    'has_receipts' => 'Un ordine di acquisto con ricezioni o residui chiusi non può tornare in bozza, in validazione né essere annullato.',
    'not_receivable' => 'Le ricezioni si registrano solo su ordini approvati, inviati o parzialmente evasi.',
    'cancelled' => 'L’ordine di acquisto è annullato.',
    'line_not_receivable' => 'La riga non appartiene all’ordine o ha il residuo chiuso.',
    'quantity_exceeds_residual' => 'La quantità supera il residuo da ricevere (:residual).',
    'receipt_below_invoiced' => 'La ricezione non può essere annullata: il ricevuto scenderebbe sotto la quantità già fatturata.',
    'line_already_complete' => 'La riga è già completa o ha il residuo chiuso.',
    'residual_below_billed' => 'Il residuo non può essere chiuso: la quantità fatturata supera il ricevuto.',
    'residual_not_closed' => 'Il residuo della riga non è chiuso.',
    'only_service_fields' => 'Dopo l’approvazione si possono modificare solo note, tag EA, pagamento, sede operativa e collegamenti: fornitore, data, descrizione, sede e righe sono bloccati.',
];
