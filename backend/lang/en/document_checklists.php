<?php

return [

    // Spec 0403, D-4: shown on `code` when a synced checklist's code is changed.
    'code_locked' => 'The code can no longer be changed after the first synchronization with ArchivioSmart.',

    // Spec 0410.
    'category_name_taken' => 'A category with this name already exists.',
    'category_has_checklists' => 'The category still contains :count checklists: move them to another category or delete them first.',

    'duplicate_file_types' => 'The allowed file types cannot be repeated.',

    // Spec 0427.
    'duplicate_section_name' => 'Two sections cannot have the same name.',
    'section_index_out_of_range' => 'The selected section does not exist.',
    'date_field_needs_max_age' => 'The extracted date field can only be set together with the maximum age.',

    // Validation attribute names. `item_<field>` maps to `items.*.<field>`.
    'attributes' => [
        'name' => 'name',
        'code' => 'code',
        'description' => 'description',
        'is_active' => 'active status',
        'sort_order' => 'sort order',
        'category_id' => 'category',
        'sections' => 'sections',
        'section_id' => 'section',
        'section_name' => 'section name',
        'section_description' => 'section description',
        'items' => 'items',
        'item_id' => 'item',
        'item_name' => 'item name',
        'item_description' => 'item description',
        'item_section_index' => 'section',
        'item_is_required' => 'required',
        'item_allowed_file_types' => 'allowed file types',
        'item_max_file_size_kb' => 'maximum file size',
        'item_min_files' => 'minimum number of files',
        'item_max_files' => 'maximum number of files',
        'item_validation_prompt' => 'verification instructions',
        'item_max_age_days' => 'maximum age in days',
        'item_date_field' => 'extracted date field',
    ],

];
