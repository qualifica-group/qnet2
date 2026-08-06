<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notification detail card
|--------------------------------------------------------------------------
|
| Labels of the "scheda dettagli" every notification email carries about the
| record it talks about (direttiva utente 2026-08-04).
|
| A DEDICATED file and not `lang/it.json` on purpose: these are generic
| single words ("Name", "Source", "Status"). As JSON keys they would be the
| translation of that word EVERYWHERE `__()` is called with it, in any
| future feature. Namespaced here, they translate this table and nothing
| else.
|
*/

return [

    'table' => [
        'field' => 'Field',
        'value' => 'Value',
    ],

    'fields' => [
        // Anagrafica (Registry)
        'name' => 'Name',
        'type' => 'Type',
        'account_managers' => 'Account managers',

        // Opportunita' / Gestione richieste (Opportunity)
        'title' => 'Title',
        'client' => 'Client',
        'operational_site' => 'Operational site',
        'status' => 'Status',
        'operator' => 'Operator',

        // Shared
        'source' => 'Source',
        'supervisor' => 'Supervisor',

        // Trasferimento contatto
        'contact' => 'Contact',
        'origin_site' => 'Origin site',
        'destination_site' => 'Destination site',
        'previous_operator' => 'Previous operator',
        'new_operator' => 'New operator',
        'performed_by' => 'Performed by',
        'date' => 'Date',
    ],

    'values' => [
        'client' => 'Client',
        'supplier' => 'Supplier',
        'empty' => '—',
    ],

];
