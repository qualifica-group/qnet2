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
    'invalid_quote_line_id' => 'Una riga inviata non appartiene a questo preventivo o al tipo di riga.',
];
