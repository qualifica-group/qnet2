<?php

return [
    'not_configured' => 'Formalab non e\' configurato (URL o credenziali mancanti).',
    'login_failed' => 'Autenticazione su Formalab non riuscita.',
    'unreachable' => 'Formalab non e\' raggiungibile.',
    'http_error' => 'Formalab ha risposto con un errore (HTTP :status).',
    'not_eligible' => 'Questa commessa non ha i campi corso, quindi non puo\' essere collegata a una classe Formalab.',
    'class_not_found' => 'La classe non esiste su Formalab.',
    'not_linked' => 'La commessa non e\' collegata a nessuna edizione Formalab.',
    'invalid_token' => 'Token Formalab non valido o mancante.',
    'sync_queued' => 'Sincronizzazione Formalab accodata.',
    'course_fields_missing' => 'Campi corso non piu\' presenti tra gli attributi, quindi non aggiornati: :codes.',
    'health' => [
        'not_probed' => 'Connessione non verificata: configurazione incompleta.',
        'never_synced' => 'Il catalogo delle classi non e\' mai stato sincronizzato.',
        'last_sync_failed' => 'L\'ultima sincronizzazione e\' fallita.',
        'sync_stale' => 'L\'ultima sincronizzazione e\' piu\' vecchia del previsto.',
        'recent_failures' => 'Chiamate Formalab fallite nelle ultime 24 ore.',
        'course_fields_missing' => 'Alcuni campi corso non esistono piu\' tra gli attributi (codice rinominato o eliminato) e non vengono aggiornati.',
    ],
];
