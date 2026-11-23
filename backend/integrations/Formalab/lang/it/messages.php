<?php

return [
    'not_configured' => 'Formalab non e\' configurato (URL o credenziali mancanti).',
    'login_failed' => 'Autenticazione su Formalab non riuscita.',
    'unreachable' => 'Formalab non e\' raggiungibile.',
    'http_error' => 'Formalab ha risposto con un errore (HTTP :status).',
    'not_eligible' => 'La categoria di questa commessa non e\' collegata a Formalab, oppure la sua mappatura non porta il Nome corso / edizione su un campo della commessa.',
    'class_not_found' => 'La classe non esiste su Formalab.',
    'course_name_missing' => 'Compila il campo della commessa mappato sul Nome corso / edizione: Formalab trova l\'edizione da quel nome.',
    'course_name_unmatched' => 'Nessuna edizione Formalab ha questo Nome corso / edizione. Controlla che sia scritto esattamente come su Formalab (il catalogo si aggiorna ogni 10 minuti).',
    'course_name_ambiguous' => 'Piu\' edizioni Formalab hanno questo Nome corso / edizione: non e\' possibile scegliere quale sincronizzare.',
    'invalid_token' => 'Token Formalab non valido o mancante.',
    'sync_queued' => 'Sincronizzazione Formalab accodata.',
    'course_fields_missing' => 'Campi mappati non piu\' presenti tra gli attributi, quindi non aggiornati: :codes.',
    'category' => [
        'enabled_inherited' => 'Il collegamento a Formalab si decide sulla categoria radice: questa categoria lo eredita.',
        'attribute_not_in_category' => 'Questo campo non e\' un attributo commessa della categoria.',
        'attribute_incompatible' => 'Il tipo di questo campo non puo\' contenere il valore di Formalab.',
        'key_missing' => 'Mappa il Nome corso / edizione: la commessa trova l\'edizione Formalab da quel campo.',
    ],
    'health' => [
        'not_probed' => 'Connessione non verificata: configurazione incompleta.',
        'never_synced' => 'Il catalogo delle classi non e\' mai stato sincronizzato.',
        'last_sync_failed' => 'L\'ultima sincronizzazione e\' fallita.',
        'sync_stale' => 'L\'ultima sincronizzazione e\' piu\' vecchia del previsto.',
        'recent_failures' => 'Chiamate Formalab fallite nelle ultime 24 ore.',
        'course_fields_missing' => 'Alcuni campi mappati nelle categorie non esistono piu\' tra gli attributi (codice rinominato o eliminato) e non vengono aggiornati.',
    ],
];
