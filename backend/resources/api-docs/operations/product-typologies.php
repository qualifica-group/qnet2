<?php

return [
    'POST /product-typologies' => [
        'summary' => 'Create a product typology',
        'description' => 'Creates a typology classifying products, with a unique name, a unique code (lowercase letters, digits and underscores, starting with a letter, not editable later), a badge color from the fixed palette (slate, gray, red, orange, amber, yellow, green, emerald, teal, blue, indigo, violet, purple, pink), an optional description, supplier_commission_enabled and proforma_kind (consultancy or institution: which proforma request bills the lines of its products; null = never billed by a proforma request). Returns 201 with the typology and the user\'s permissions. Requires product-typologies.create.',
    ],
    'GET /product-typologies/for-select' => [
        'summary' => 'Search product typologies for a selector',
        'description' => 'Returns a paginated list of typologies ordered by name, each as id, label (name) and meta code and color. Supports search on the name, offset and limit (default 25, max 100) and ids[] to append already selected typologies. Any authenticated user.',
    ],
    'GET /product-typologies/{productTypology}' => [
        'summary' => 'Get a product typology',
        'description' => 'Returns the typology with the user\'s permissions on it (resource, fields, actions). Requires product-typologies.view.',
    ],
    'PUT /product-typologies/{productTypology}' => [
        'summary' => 'Update a product typology',
        'description' => 'Updates the submitted fields (name, description, color, supplier_commission_enabled, proforma_kind); the code cannot be changed (422 if sent). Returns the typology with the user\'s permissions. Requires product-typologies.update.',
    ],
    'DELETE /product-typologies/{productTypology}' => [
        'summary' => 'Delete a product typology',
        'description' => 'Deletes the typology and answers 204 with no content. 409 when products still use it. Requires product-typologies.delete.',
    ],
];
