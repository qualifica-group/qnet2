<?php

return [
    'GET /meta/{resource}' => [
        'summary' => 'Get the field and permission metadata of a resource',
        'description' => 'For a resource key (for example leads, users, financial-accounts) returns data.fields, the catalogue of its fields (key, type, group, mandatory; custom fields with label, configuration and options), and the permissions block of the caller in the create context: resource abilities, per-field visible/editable/required flags and actions. Use it to know which fields can be sent when creating a record. 404 when the resource key is unknown. Requires {resource}.viewAny or {resource}.view.',
    ],
];
