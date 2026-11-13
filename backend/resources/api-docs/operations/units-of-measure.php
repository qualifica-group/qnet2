<?php

return [
    'POST /units-of-measure' => [
        'summary' => 'Create a unit of measure',
        'description' => 'Creates a unit of measure used on products and on quote, purchase order, purchase request and work order cost lines: name and symbol (both required and unique), code (required, unique, lowercase letters, digits and underscores starting with a letter, never changeable afterwards) and an optional description. Returns 201 with the unit and the caller\'s resource, field and action permissions. Requires units-of-measure.create.',
    ],
    'GET /units-of-measure/for-select' => [
        'summary' => 'List units of measure for a dropdown',
        'description' => 'Returns units of measure as id, label (name), subtitle and meta.symbol, ordered by name, filtered by search (name contains) and paginated with offset and limit; ids[] also returns those units for display of a saved value. Response is a list (items + pagination). Requires authentication only.',
    ],
    'GET /units-of-measure/{unitOfMeasure}' => [
        'summary' => 'Get a unit of measure',
        'description' => 'Returns the unit of measure (code, name, symbol, description, timestamps) plus the caller\'s resource, field and action permissions on it. Requires units-of-measure.view.',
    ],
    'PUT /units-of-measure/{unitOfMeasure}' => [
        'summary' => 'Update a unit of measure',
        'description' => 'Partially updates the unit\'s name, symbol (both unique) and description and returns it with the caller\'s permissions. code must not be sent (422). Requires units-of-measure.update.',
    ],
    'DELETE /units-of-measure/{unitOfMeasure}' => [
        'summary' => 'Delete a unit of measure',
        'description' => 'Deletes the unit of measure. 409 when products, quote lines, purchase order lines, purchase request lines or work order costs still use it, with a message naming the uses and their counts. Returns 204 no content. Requires units-of-measure.delete.',
    ],
];
