<?php

return [

    // The `{category.key}` variable catalogue (spec 0175, D-4), returned by
    // GET /api/email-templates/variables. Only `work_order`/`sender` are
    // declared here: every other category's labels are reused verbatim from
    // `document_layouts.variables.*` (App\Services\OutboundEmails\WorkOrderEmailVariableCatalog).
    'variables' => [
        'categories' => [
            'work_order' => 'Commessa',
            'reminder' => 'Sollecito',
            'sender' => 'Mittente',
        ],

        'work_order' => [
            'code' => 'Codice commessa',
            'title' => 'Titolo',
            'type' => 'Tipo',
            'start_date' => 'Data avvio',
            'callback_date' => 'Data richiamo',
            'description' => 'Descrizione',
        ],

        'reminder' => [
            'overdue_installments' => 'Elenco rate scadute',
            'overdue_amount' => 'Importo scaduto',
        ],

        'sender' => [
            'name' => 'Nome',
            'email' => 'Email',
        ],
    ],

];
