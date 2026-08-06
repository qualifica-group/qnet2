<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Scheda dettagli delle notifiche
|--------------------------------------------------------------------------
|
| Traduzione italiana delle etichette della scheda dettagli. Vedi
| `lang/en/notifications.php` per il motivo per cui NON stanno in it.json.
|
| I nomi seguono la terminologia gia' usata nel gestionale: "Gestori
| Account" (non "Account manager"), "Sede operativa", "Stato di lavorazione".
|
*/

return [

    'table' => [
        'field' => 'Campo',
        'value' => 'Valore',
    ],

    'fields' => [
        // Anagrafica
        'name' => 'Denominazione',
        'type' => 'Tipo',
        'account_managers' => 'Gestori Account',

        // Opportunita' / Gestione richieste
        'title' => 'Titolo',
        'client' => 'Cliente',
        'operational_site' => 'Sede operativa',
        'status' => 'Stato',
        'operator' => 'Operatore',

        // Comuni
        'source' => 'Fonte',
        'supervisor' => 'Supervisore',

        // Trasferimento contatto
        'contact' => 'Contatto',
        'origin_site' => 'Sede di provenienza',
        'destination_site' => 'Sede di destinazione',
        'previous_operator' => 'Operatore precedente',
        'new_operator' => 'Nuovo operatore',
        'performed_by' => 'Eseguito da',
        'date' => 'Data',
    ],

    'values' => [
        'client' => 'Cliente',
        'supplier' => 'Fornitore',
        'empty' => '—',
    ],

];
