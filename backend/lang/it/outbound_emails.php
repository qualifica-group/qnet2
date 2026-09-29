<?php

return [

    // Microsoft Graph transport errors (spec 0175, D-1/D-6): thrown by
    // App\Services\Graph\GraphMailException, built from a SAFE, static
    // vocabulary only -- never the raw Graph response body nor the access
    // token (see App\Services\Graph\GraphMailClient).
    'graph_invalid_sender' => 'La casella mittente :email non è una casella Microsoft 365 valida.',
    'graph_auth_error' => 'Configurazione Microsoft Graph non valida: verifica tenant, client e secret.',
    'graph_generic_error' => 'Errore Microsoft Graph (:code): invio non riuscito.',

    // SendOutboundEmailJob (D-12): fallback error_message for a mailer
    // failure that is not a GraphMailException, and for a queue worker that
    // kills the job before its own try/catch runs (failed()).
    'job_generic_failure' => 'Invio non riuscito: riprova più tardi.',

    // BE-05: work-orders/{workOrder}/emails/* write endpoints (spec 0175,
    // D-2/D-5/D-7/D-12). 409 body/errors on a status conflict; 422 field
    // errors on send/import.
    'not_draft' => 'Questa email non è più una bozza: non è più modificabile.',
    'not_author' => 'Solo l\'autore di questa email può inviarla.',
    'not_sendable_status' => 'Questa email non può essere inviata dal suo stato attuale.',
    'no_recipients' => 'Aggiungi almeno un destinatario in "A".',
    'empty_subject' => 'L\'oggetto è obbligatorio per inviare l\'email.',
    'empty_body' => 'Il corpo dell\'email è obbligatorio per l\'invio.',
    'sender_email_missing' => 'Il tuo utente non ha un indirizzo email: impossibile inviare.',
    'too_many_recipients' => 'Puoi indicare al massimo :max destinatari complessivi (A + CC + CCN).',
    'attachments_limit_exceeded' => 'Il totale degli allegati supera il limite di :max_kb KB.',
    'template_not_available' => 'Il modello selezionato non è disponibile per questo modulo.',
    'attachment_not_available' => 'Uno o più file selezionati non sono disponibili per questa commessa.',
    'document_bundle_not_available' => 'Il modello documenti selezionato non è disponibile.',

    // WorkOrderEmailComposeContextBuilder (D-5): suggerimento etichetta per
    // i responsabili/partecipanti della commessa.
    'recipient_supervisor' => 'Responsabile',
    'recipient_participant' => 'Partecipante',

];
