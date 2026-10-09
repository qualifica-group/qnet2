<?php

/*
|--------------------------------------------------------------------------
| Quick-search of the tables (spec 0179)
|--------------------------------------------------------------------------
*/

return [

    // Gestione Richieste / Gestione Iscritti: maximum registries read by each
    // first pass (card, contacts). A very generic term shows at most the
    // requests of these registries; kept well below the 65535 bound
    // parameters a MySQL statement accepts.
    'request_client_match_cap' => (int) env('REQUEST_CLIENT_SEARCH_MATCH_CAP', 5000),

    // Anagrafiche (spec 0211): maximum ids read by each lookup (card, phones,
    // referents, referent links) before the single `registries.id IN` branch.
    'registry_match_cap' => (int) env('REGISTRY_SEARCH_MATCH_CAP', 5000),

];
