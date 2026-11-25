<?php

return [
    // Esiti della sincronizzazione checklist (salvati in sync_error e nel log).
    'not_configured' => 'Connessione ArchivioSmart non configurata',
    'unauthorized' => 'Token ArchivioSmart non valido',
    'conflict' => "Esiste gia' in ArchivioSmart una checklist con questo identificativo non creata da QNet 2",
    'category_conflict' => "Esiste gia' in ArchivioSmart una categoria non creata da QNet 2 con questo identificativo",
    'category_not_synced' => 'Categoria non sincronizzata: :reason',
    'validation_failed' => 'ArchivioSmart ha rifiutato la checklist',
    'server_error' => 'ArchivioSmart ha risposto con un errore del server (:status)',
    'network_error' => 'Impossibile contattare ArchivioSmart',
    'unexpected_status' => 'Risposta imprevista da ArchivioSmart (:status)',
    'job_generic_failure' => 'Sincronizzazione con ArchivioSmart interrotta in modo imprevisto',

    // Prova connessione.
    'connection_not_configured' => 'Connessione ArchivioSmart non configurata',
    'connection_ok' => 'Connessione ad ArchivioSmart riuscita',
    'connection_failed' => 'Connessione ad ArchivioSmart non riuscita (:status)',

    // Richieste di documenti (spec 0428).
    'request_conflict' => "ArchivioSmart ha rifiutato la richiesta: esiste gia' una richiesta con questo identificativo",
    'request_status_closed' => "La richiesta non e' piu' modificabile",
    'request_not_remindable' => "Si puo' sollecitare solo una richiesta inviata o in corso",
    'request_not_cancellable' => "Una richiesta completata o scaduta non si puo' annullare",
    'request_not_reviewable' => "Si puo' lavorare solo su una richiesta inviata o in corso",
    'request_not_completable' => "Una richiesta in bozza, scaduta o annullata non si puo' chiudere",
    'request_item_not_found' => 'La voce non appartiene alla richiesta',
    'request_item_without_document' => 'La voce non ha nessun file caricato',
    'request_action_unavailable' => 'ArchivioSmart non supporta ancora questa azione',
    'document_download_failed' => 'Impossibile scaricare il file da ArchivioSmart',
    'document_not_found' => 'File non trovato',
    'webhook_not_found' => 'Non trovato',
    'webhook_invalid_signature' => 'Firma non valida',

    'attributes' => [
        'base_url' => 'URL base',
        'token' => 'token',
        'due_date' => 'scadenza',
        'recipient_email' => 'email del destinatario',
        'message' => 'messaggio',
        'document_checklist_id' => 'checklist',
        'webhook_secret' => 'segreto del webhook',
    ],
];
