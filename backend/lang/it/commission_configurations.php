<?php

return [
    'roles' => [
        'commercial' => 'Commerciale',
        'reporter' => 'Segnalatore',
        'supervisor' => 'Supervisore',
        'supplier' => 'Fornitore',
    ],
    'scopes' => [
        'product_category' => 'Categoria prodotto',
        'product' => 'Prodotto',
        'recipient' => 'Destinatario',
    ],
    'types' => [
        'fixed_amount' => 'Importo fisso',
        'percentage' => 'Percentuale',
    ],
    'statuses' => [
        'active' => 'Attiva',
        'suspended' => 'Sospesa',
    ],
    'invalid_scope' => "L'ambito selezionato non corrisponde alla categoria o al prodotto.",
    'referenced_delete' => 'Questa configurazione commissioni è utilizzata e non può essere eliminata.',
    'invalid_recipient' => 'Il destinatario della commissione non è compatibile con il ruolo.',
    'recipient_type_not_allowed' => 'Il tipo di destinatario non è ammesso per questo ruolo.',
    'recipient_not_selected' => 'Il destinatario della commissione deve essere la persona selezionata sull\'offerta per questo ruolo.',
    'role_without_recipient' => 'Nessun destinatario è selezionato sull\'offerta per questo ruolo, quindi non è commissionabile.',
    'invalid_quote_line_id' => 'Una riga inviata non appartiene a questo preventivo o al tipo di riga.',
    'recipient_role_changed' => 'Il ruolo è cambiato di tipo destinatario: indica il nuovo destinatario o rimuovilo.',
];
