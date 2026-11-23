<?php

return [
    // Esiti della sincronizzazione checklist (salvati in sync_error e nel log).
    'not_configured' => 'Connessione ArchivioSmart non configurata',
    'unauthorized' => 'Token ArchivioSmart non valido',
    'conflict' => "Esiste gia' in ArchivioSmart una checklist con questo identificativo non creata da QNet 2",
    'validation_failed' => 'ArchivioSmart ha rifiutato la checklist',
    'server_error' => 'ArchivioSmart ha risposto con un errore del server (:status)',
    'network_error' => 'Impossibile contattare ArchivioSmart',
    'unexpected_status' => 'Risposta imprevista da ArchivioSmart (:status)',
    'job_generic_failure' => 'Sincronizzazione con ArchivioSmart interrotta in modo imprevisto',

    // Prova connessione.
    'connection_not_configured' => 'Connessione ArchivioSmart non configurata',
    'connection_ok' => 'Connessione ad ArchivioSmart riuscita',
    'connection_failed' => 'Connessione ad ArchivioSmart non riuscita (:status)',

    'attributes' => [
        'base_url' => 'URL base',
        'token' => 'token',
    ],
];
