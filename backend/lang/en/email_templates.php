<?php

return [

    // The `{category.key}` variable catalogue (spec 0175, D-4), returned by
    // GET /api/email-templates/variables. Only `work_order`/`sender` are
    // declared here: every other category's labels are reused verbatim from
    // `document_layouts.variables.*` (App\Services\OutboundEmails\WorkOrderEmailVariableCatalog).
    'variables' => [
        'categories' => [
            'work_order' => 'Work order',
            'reminder' => 'Reminder',
            'sender' => 'Sender',
        ],

        'work_order' => [
            'code' => 'Work order code',
            'title' => 'Title',
            'type' => 'Type',
            'start_date' => 'Start date',
            'callback_date' => 'Callback date',
            'description' => 'Description',
        ],

        'reminder' => [
            'overdue_installments' => 'Overdue installments list',
            'overdue_amount' => 'Overdue amount',
        ],

        'sender' => [
            'name' => 'Name',
            'email' => 'Email',
        ],
    ],

];
