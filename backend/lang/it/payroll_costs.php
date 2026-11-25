<?php

return [
    'import' => [
        'errors' => [
            'unreadable_file' => 'Il file non può essere letto: usa un file xlsx, csv o txt.',
            'empty_file' => "Il file è vuoto: servono l'intestazione e almeno una riga.",
            'missing_columns' => 'Colonne mancanti nel file: :columns.',
            'too_many_rows' => 'Il file supera il limite di :max righe.',
            'required' => 'Valore obbligatorio.',
            'invalid_amount' => 'Importo non numerico.',
            'invalid_date' => 'Data non valida: usa il formato gg/mm/aaaa.',
            'invalid_month' => 'Mese non valido.',
            'invalid_year' => 'Anno non valido.',
            'other_period' => 'Il mese è diverso da quello delle altre righe (:period): un file contiene un solo mese.',
            'total_mismatch' => 'Il totale non corrisponde a retribuzione + contributivo + INAIL.',
            'tax_code_not_found' => 'Codice fiscale non trovato.',
            'tax_code_many_users' => 'Codice fiscale su più utenti.',
            'company_unmapped' => 'Società non associata.',
            'duplicate' => "Società e codice fiscale già presenti in un'altra riga del file.",
        ],
    ],
];
