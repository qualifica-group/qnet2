<?php

return [
    'no_working_day' => 'Il periodo selezionato non contiene giorni lavorativi.',
    'calendar_period_too_long' => 'Il periodo del calendario puo\' coprire al massimo :max giorni.',
    'minutes_out_of_range' => 'I minuti richiesti devono essere tra 1 e :max.',
    'overlap' => 'Il periodo si sovrappone a un\'altra richiesta di assenza della stessa persona.',
    'no_approvers' => 'Nessun approvatore disponibile per questa persona.',
    'not_draft' => 'Solo una richiesta in bozza puo\' essere modificata.',
    'not_submitted' => 'Solo una richiesta inviata puo\' essere decisa.',
    'not_cancellable' => 'Solo una richiesta inviata o approvata puo\' essere annullata.',
    'already_started' => 'Una richiesta approvata gia\' iniziata puo\' essere annullata solo dall\'ufficio del personale.',

    'notification' => [
        'submitted' => [
            'title' => 'Richiesta di assenza da approvare',
            'approver' => ':person ha inviato una richiesta di assenza (:type, :period).',
            'office' => ':person ha inviato una richiesta di assenza (:type, :period).',
        ],
        'approved' => [
            'title' => 'Richiesta di assenza approvata',
            'person' => 'La tua richiesta di assenza (:type, :period) e\' stata approvata da :actor.',
            'automatic_person' => 'La tua richiesta di assenza (:type, :period) e\' stata registrata: questo tipo non richiede approvazione.',
            'automatic_approver' => ':person ha registrato un\'assenza (:type, :period) che non richiede approvazione.',
            'approver' => 'La richiesta di assenza di :person (:type, :period) e\' stata approvata da :actor.',
            'office' => 'La richiesta di assenza di :person (:type, :period) e\' stata approvata da :actor.',
            'automatic_office' => ':person ha registrato un\'assenza (:type, :period) che non richiede approvazione.',
        ],
        'rejected' => [
            'title' => 'Richiesta di assenza rifiutata',
            'person' => 'La tua richiesta di assenza (:type, :period) e\' stata rifiutata da :actor.',
            'approver' => 'La richiesta di assenza di :person (:type, :period) e\' stata rifiutata da :actor.',
            'office' => 'La richiesta di assenza di :person (:type, :period) e\' stata rifiutata da :actor.',
        ],
        'cancelled' => [
            'title' => 'Richiesta di assenza annullata',
            'person' => ':actor ha annullato la tua richiesta di assenza (:type, :period).',
            'approver' => ':actor ha annullato la richiesta di assenza di :person (:type, :period).',
            'office' => ':actor ha annullato la richiesta di assenza di :person (:type, :period).',
        ],
        'reason' => 'Motivo: :reason',
    ],
];
