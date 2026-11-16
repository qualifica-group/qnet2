<?php

return [
    'POST /attributes' => [
        'summary' => 'Create a product attribute',
        'description' => 'Creates a dynamic attribute that product categories can assign to products, quotes and work orders. code is unique, lowercase letters, digits and underscores; type is one of text, textarea, integer, decimal, boolean, enum, relation, date, datetime, time, email, url, color, table. An enum needs at least one option with unique values; a relation needs relation_target with entity_type, cardinality one or many and for_select_resource; a table needs a valid config (422 otherwise). Returns 201 with the attribute, its options and the permissions block. Requires attributes.create.',
    ],
    'GET /attributes/{attribute}' => [
        'summary' => 'Get a product attribute',
        'description' => 'Returns the attribute definition (code, name, type, help text, placeholder, icon, config, relation target) with its enum options and the actor\'s permissions block. Requires attributes.view.',
    ],
    'PUT /attributes/{attribute}' => [
        'summary' => 'Update a product attribute',
        'description' => 'Partially updates the attribute; when options is sent it fully replaces the existing option list. The type rules of create are checked against the resulting values (e.g. switching to enum without options already stored answers 422). Returns the updated attribute with options and the permissions block. Requires attributes.update.',
    ],
    'DELETE /attributes/{attribute}' => [
        'summary' => 'Delete a product attribute',
        'description' => 'Deletes the attribute. Refused with 409, listing the uses, while it is assigned to a product category or a value is recorded under its code on products, quotes or work orders. Returns 204 no content. Requires attributes.delete.',
    ],
];
