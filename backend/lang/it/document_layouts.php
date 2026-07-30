<?php

return [

    'modules' => [
        'quotes' => 'Preventivi',
    ],

    // Business messages (D-7, spec 0069): thrown by
    // App\Services\DocumentLayouts\DocumentLayoutDefaultManager as a 422
    // ValidationException keyed on `is_active`/`is_default`.
    'default_requires_active' => 'Un layout predefinito deve essere attivo.',
    'default_cannot_be_deactivated' => 'Il layout predefinito non può essere disattivato: designa prima un altro layout come predefinito.',
    'default_must_be_reassigned' => 'Il layout predefinito non può essere rimosso direttamente: designa prima un altro layout come predefinito.',
    'default_cannot_be_deleted' => 'Il layout predefinito non può essere eliminato finché esistono altri layout dello stesso modulo: designa prima un altro layout come predefinito.',

    // Image guard (spec 0069, MT-4).
    'image_in_use' => 'Questa immagine è referenziata da un blocco della configurazione corrente: rimuovi il blocco prima di eliminarla.',

    'variables' => [
        'categories' => [
            'quote' => 'Preventivo',
            'totals' => 'Totali',
            'client' => 'Cliente',
            'opportunity' => 'Opportunità',
            'referent' => 'Referente',
            'commercial' => 'Commerciale',
            'reporter' => 'Segnalatore',
            'supervisor' => 'Supervisore',
            'company' => 'Società',
            'company_site' => 'Sede',
            'operational_site' => 'Sede operativa',
            'custom_fields' => 'Campi personalizzati',
            'opportunity_attributes' => 'Attributi opportunità',
            'document' => 'Documento',
        ],

        'quote' => [
            'code' => 'Codice preventivo',
            'title' => 'Titolo',
            'internal_notes' => 'Note interne',
            'status_name' => 'Stato',
            'created_at' => 'Data creazione',
            'updated_at' => 'Data ultima modifica',
        ],

        'totals' => [
            'revenue_net' => 'Ricavi netti',
            'revenue_vat' => 'IVA sui ricavi',
            'revenue_gross' => 'Ricavi lordi',
            'cost_net' => 'Costi netti',
            'cost_vat' => 'IVA sui costi',
            'cost_gross' => 'Costi lordi',
            'margin_net' => 'Margine netto',
        ],

        'client' => [
            'name' => 'Nome',
            'full_name' => 'Nome e cognome',
            'type' => 'Tipo',
            'vat_number' => 'Partita IVA',
            'tax_code' => 'Codice fiscale',
            'sdi_code' => 'Codice SDI',
            'email' => 'Email',
            'phone' => 'Telefono',
            'address' => 'Indirizzo',
            'address_line1' => 'Indirizzo (via)',
            'address_postal_code' => 'CAP',
            'address_city' => 'Comune',
            'address_province' => 'Provincia',
            'address_state' => 'Regione',
            'address_country' => 'Nazione',
        ],

        'opportunity' => [
            'name' => 'Nome opportunità',
            'status_name' => 'Stato',
            'estimated_value' => 'Valore stimato',
            'expected_close_date' => 'Data chiusura prevista',
            'start_date' => 'Data avvio',
            'general_notes' => 'Note generali',
        ],

        'referent' => [
            'name' => 'Nome',
            'type_name' => 'Tipo referente',
            'email' => 'Email',
            'phone' => 'Telefono',
        ],

        'commercial' => [
            'name' => 'Nome',
            'email' => 'Email',
            'phone' => 'Telefono',
        ],

        'reporter' => [
            'name' => 'Nome',
            'email' => 'Email',
            'phone' => 'Telefono',
        ],

        'supervisor' => [
            'name' => 'Nome',
            'email' => 'Email',
        ],

        'company' => [
            'denomination' => 'Ragione sociale',
            'vat_number' => 'Partita IVA',
            'address' => 'Indirizzo',
            'address_city' => 'Comune',
            'address_postal_code' => 'CAP',
        ],

        'company_site' => [
            'name' => 'Nome sede',
            'bank_name' => 'Banca',
            'bank_iban' => 'IBAN',
            'address' => 'Indirizzo',
            'address_city' => 'Comune',
            'address_postal_code' => 'CAP',
        ],

        'operational_site' => [
            'label' => 'Sede operativa',
        ],

        'document' => [
            'generated_at' => 'Data generazione',
            'generated_by' => 'Generato da',
        ],
    ],

];
