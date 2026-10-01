<?php

/**
 * Stringhe del check System Health (spec 0187). Solo messaggi safe: nessun
 * dettaglio di eccezione/classe interna ne' valore di segreto.
 */
return [

    'ok' => 'Stato di sistema recuperato.',

    'check_failed' => 'Controllo non disponibile.',

    'database' => [
        'unreachable' => 'Database non raggiungibile.',
    ],

    'email' => [
        'transport_unavailable' => 'Trasporto email non disponibile.',
    ],

    'queue' => [
        'unreachable' => 'Tabelle della coda non raggiungibili.',
    ],

];
