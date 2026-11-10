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

        // Task (spec 0119)
        'priority' => 'Priorità',
        'end_date' => 'Data fine',
        'requester' => 'Richiedente',
        'assignees' => 'Assegnatari',

        // Commessa (spec 0351)
        'start_date' => 'Data inizio',
        'supervisors' => 'Responsabili',
        'participants' => 'Partecipanti',

        // Dati fiscali dell'anagrafica (spec 0381)
        'vat_number' => 'Partita IVA',
        'tax_code' => 'Codice fiscale',
        'sdi_code' => 'Codice SDI',

        // Assenza (spec 0381)
        'person' => 'Persona',
        'period' => 'Periodo',
        'reason' => 'Motivazione',

        // Richiesta di modifica (spec 0381)
        'field' => 'Campo',
        'current_value' => 'Valore attuale',
        'requested_value' => 'Valore richiesto',
        'outcome' => 'Esito',
        'note' => 'Nota',
        'handled_by' => 'Gestita da',

        // Riga di commessa consegnabile (spec 0381)
        'product' => 'Prodotto',
        'payment_status' => 'Stato pagamento',

        // Referente, richiesta proforma, richiesta di acquisto (spec 0381)
        'referent_type' => 'Tipo referente',
        'registries' => 'Anagrafiche',
        'work_order' => 'Commessa',
        'supplier' => 'Fornitore',
        'assignee' => 'Assegnata a',
        'subject' => 'Oggetto',
        'requested_on' => 'Data richiesta',
        'total' => 'Totale',
    ],

    'values' => [
        'client' => 'Cliente',
        'supplier' => 'Fornitore',
        'empty' => '—',
        'approved' => 'Approvata',
        'rejected' => 'Rifiutata',
        'proforma_consultancy' => 'Consulenza',
        'proforma_institution' => 'Ente',
        'proforma_pending' => 'Da evadere',
        'proforma_issued' => 'Evasa',
    ],

    /*
    | Etichetta del bottone che apre il modulo (spec 0381, D-2): la chiave e'
    | il primo segmento del percorso di destinazione.
    */
    'actions' => [
        'registries' => 'Apri anagrafica',
        'referents' => 'Apri referente',
        'opportunities' => 'Apri opportunità',
        'quotes' => 'Apri offerta',
        'request-management' => 'Apri richiesta',
        'enrollee-management' => 'Apri iscritto',
        'work-orders' => 'Apri commessa',
        'tasks' => 'Apri attività',
        'purchase-requests' => 'Apri richiesta di acquisto',
        'proforma-requests' => 'Apri richiesta proforma',
        'absences' => 'Apri assenze',
        'field-change-requests' => 'Apri richiesta di modifica',
        'default' => 'Apri in QNet',
    ],

];
