<?php

return [
    'statement_too_many_rows' => 'Troppe righe: restringi il periodo.',
    'opening_after_movement' => 'La data di apertura non puo\' essere successiva a un movimento manuale gia\' registrato sul conto.',
    'columns' => [
        'date' => 'Data',
        'type' => 'Tipo',
        'description' => 'Descrizione',
        'registry' => 'Anagrafica',
        'document' => 'Documento',
        'work_order' => 'Commessa',
        'inflow' => 'Entrate',
        'outflow' => 'Uscite',
        'balance' => 'Saldo',
    ],
    'types' => [
        'manual_income' => 'Entrata',
        'manual_expense' => 'Uscita',
        'transfer' => 'Giroconto',
        'adjustment' => 'Rettifica',
        'collection' => 'Incasso',
        'payment' => 'Pagamento',
    ],
];
