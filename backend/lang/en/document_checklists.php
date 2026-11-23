<?php

return [

    // Spec 0403, D-4: shown on `code` when a synced checklist's code is changed.
    'code_locked' => 'The code can no longer be changed after the first synchronization with ArchivioSmart.',

    // Spec 0410.
    'category_name_taken' => 'A category with this name already exists.',
    'category_has_checklists' => 'The category still contains :count checklists: move them to another category or delete them first.',

    'duplicate_file_types' => 'The allowed file types cannot be repeated.',

    // Validation attribute names. `item_<field>` maps to `items.*.<field>`.
    'attributes' => [
        'name' => 'name',
        'code' => 'code',
        'description' => 'description',
        'is_active' => 'active status',
        'sort_order' => 'sort order',
        'category_id' => 'category',
        'items' => 'items',
        'item_id' => 'item',
        'item_name' => 'item name',
        'item_description' => 'item description',
        'item_section' => 'section',
        'item_is_required' => 'required',
        'item_allowed_file_types' => 'allowed file types',
        'item_max_file_size_kb' => 'maximum file size',
        'item_min_files' => 'minimum number of files',
        'item_max_files' => 'maximum number of files',
        'item_validation_prompt' => 'verification instructions',
        'item_max_age_days' => 'maximum age in days',
    ],

];
