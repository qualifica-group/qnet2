<?php

return [
    'import' => [
        'line_description' => 'Retribuzione netta :period',
        'errors' => [
            'unreadable_file' => 'Il file non può essere letto: usa un file xlsx, csv o txt.',
            'empty_file' => "Il file è vuoto: servono l'intestazione e almeno una riga.",
            'missing_columns' => 'Colonne mancanti nel file: :columns.',
            'too_many_rows' => 'Il file supera il limite di :max righe.',
            'required' => 'Valore obbligatorio.',
            'invalid_amount' => 'Importo non numerico.',
            'non_positive_amount' => "L'importo deve essere maggiore di zero.",
            'invalid_month' => 'Mese non valido.',
            'invalid_year' => 'Anno non valido.',
            'other_period' => 'Il mese è diverso da quello delle altre righe (:period): un file contiene un solo mese.',
            'too_long' => 'Il valore supera :max caratteri.',
            'tax_code_missing' => 'Nessuna ripartizione di tipo "Codice fiscale" nella riga.',
            'tax_code_not_found' => 'Codice fiscale non trovato.',
            'tax_code_many_users' => 'Codice fiscale su più utenti.',
            'company_unmapped' => 'Società non associata.',
            'duplicate' => 'Società, persona e tipo cedolino già presenti in un\'altra riga del file.',
            'already_paid' => 'Il cedolino del mese esiste già ed è stato pagato (anche in parte): non può essere aggiornato.',
            'cost_type_missing' => 'Il tipo di costo delle retribuzioni non esiste.',
            'payment_method_missing' => 'Il metodo di pagamento dei cedolini non esiste.',
        ],
        'warnings' => [
            'supplier_many_matches' => 'Più anagrafiche con lo stesso codice fiscale: verrà creata una nuova anagrafica fornitore.',
            'iban_changed' => "L'IBAN del file è diverso da quello salvato: verrà sostituito.",
            'iban_no_profile' => "L'utente non ha un profilo di impiego: l'IBAN non verrà salvato.",
            'no_cost_row' => 'Nessun costo del personale importato per questa società, persona e mese.',
        ],
    ],
];
