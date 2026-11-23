<?php

return [

    // Spec 0403, D-4: shown on `code` when a synced checklist's code is changed.
    'code_locked' => 'Il codice non è più modificabile dopo la prima sincronizzazione con ArchivioSmart.',

    // Spec 0410.
    'category_name_taken' => 'Esiste già una categoria con questo nome.',
    'category_has_checklists' => 'La categoria contiene ancora :count checklist: spostale in un\'altra categoria o eliminale prima.',

    'duplicate_file_types' => 'I tipi di file consentiti non possono essere ripetuti.',

    // Spec 0427.
    'duplicate_section_name' => 'Due gruppi non possono avere lo stesso nome.',
    'section_index_out_of_range' => 'Il gruppo selezionato non esiste.',
    'date_field_needs_max_age' => 'Il campo data estratto si può indicare solo insieme all\'anzianità massima.',

    // Validation attribute names. `item_<field>` maps to `items.*.<field>`.
    'attributes' => [
        'name' => 'nome',
        'code' => 'codice',
        'description' => 'descrizione',
        'is_active' => 'stato attivo',
        'sort_order' => 'ordine',
        'category_id' => 'categoria',
        'sections' => 'gruppi',
        'section_id' => 'gruppo',
        'section_name' => 'nome del gruppo',
        'section_description' => 'descrizione del gruppo',
        'items' => 'voci',
        'item_id' => 'voce',
        'item_name' => 'nome della voce',
        'item_description' => 'descrizione della voce',
        'item_section_index' => 'gruppo',
        'item_is_required' => 'obbligatoria',
        'item_allowed_file_types' => 'tipi di file consentiti',
        'item_max_file_size_kb' => 'dimensione massima del file',
        'item_min_files' => 'numero minimo di file',
        'item_max_files' => 'numero massimo di file',
        'item_validation_prompt' => 'istruzioni di verifica',
        'item_max_age_days' => 'anzianità massima in giorni',
        'item_date_field' => 'campo data estratto',
    ],

];
