<?php

return [
    'GET /product-category-branches/for-select' => [
        'summary' => 'List product category branches for a select',
        'description' => 'Returns a page of active product categories that have at least one child (branch containers), as options with id and label = name, sorted by name. Used as values of quote workflow branch criteria. Optional search filters by name; offset and limit (max 100) paginate; ids[] appends those categories even when outside the page or search, for prefilling. Response holds items and pagination. Needs only authentication.',
    ],
];
