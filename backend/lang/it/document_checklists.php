<?php

return [

    // Spec 0403, D-4: shown on `code` when a synced checklist's code is changed.
    'code_locked' => 'Il codice non è più modificabile dopo la prima sincronizzazione con ArchivioSmart.',

    'duplicate_file_types' => 'I tipi di file consentiti non possono essere ripetuti.',

    // Validation attribute names. `item_<field>` maps to `items.*.<field>`.
    'attributes' => [
        'name' => 'nome',
        'code' => 'codice',
        'description' => 'descrizione',
        'is_active' => 'stato attivo',
        'items' => 'voci',
        'item_id' => 'voce',
        'item_name' => 'nome della voce',
        'item_description' => 'descrizione della voce',
        'item_section' => 'sezione',
        'item_is_required' => 'obbligatoria',
        'item_allowed_file_types' => 'tipi di file consentiti',
        'item_max_file_size_kb' => 'dimensione massima del file',
        'item_min_files' => 'numero minimo di file',
        'item_max_files' => 'numero massimo di file',
        'item_validation_prompt' => 'istruzioni di verifica',
        'item_max_age_days' => 'anzianità massima in giorni',
    ],

];
