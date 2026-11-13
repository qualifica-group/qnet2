<?php

return [
    'POST /custom-fields' => [
        'summary' => 'Create a custom field',
        'description' => 'Defines a custom field on a module (entity_type from custom-fields/entities); its records then carry the value under custom_fields.<key>. key: lowercase letters, digits, underscores, unique per entity_type; type: text, textarea, integer, decimal, boolean, enum, relation, date, datetime, time, email, url, color or table. enum needs unique options, relation needs relation_target (entity_type, cardinality one|many, for_select_resource), table a valid config and no index (422). is_indexed queues an index. Returns 201 with the definition and the permissions block. Requires custom-fields.create.',
    ],
    'GET /custom-fields/entities' => [
        'summary' => 'List the modules that support custom fields',
        'description' => 'Returns the entity types a custom field can be defined on, each with entity_type and a translation key as label; use the entity_type value when creating a custom field. Requires custom-fields.viewAny.',
    ],
    'GET /custom-fields/relation-targets' => [
        'summary' => 'List the possible targets of a relation field',
        'description' => 'Returns every entity a relation-type custom field or product attribute can point to: the modules that support custom fields plus reference-only models such as provinces, each with entity_type and a translation key as label. Use the value as relation_target.entity_type. Requires custom-fields.viewAny.',
    ],
    'GET /custom-fields/{customField}' => [
        'summary' => 'Get a custom field',
        'description' => 'Returns the custom field definition (entity type, key, type, label, layout group and tab, config, validation, relation target, indexed and active flags) with its enum options and the actor\'s permissions block. Requires custom-fields.view.',
    ],
    'PUT /custom-fields/{customField}' => [
        'summary' => 'Update a custom field',
        'description' => 'Partially updates the definition; options, when sent, fully replace the existing list. entity_type, type and key cannot change once any record holds a value for the field (422). Turning is_indexed on queues a database index build. Returns the updated definition and the permissions block. Requires custom-fields.update.',
    ],
    'DELETE /custom-fields/{customField}' => [
        'summary' => 'Delete a custom field',
        'description' => 'Deletes the definition. Refused with 409 while any record holds a value for it; deactivate it (is_active false) instead. Returns 204 no content. Requires custom-fields.delete.',
    ],
];
