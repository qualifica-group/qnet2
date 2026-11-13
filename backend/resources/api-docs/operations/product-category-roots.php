<?php

return [
    'GET /product-category-roots/for-select' => [
        'summary' => 'Search top-level product categories for a filter',
        'description' => 'Returns a paginated list of first-level product categories, inactive ones included, as id and label, used to filter lists by parent category. On the first page an "all" option with id 0 leads the list (not counted in total). current_only keeps non-legacy roots and drops the "all" option; hide_linked_legacy hides legacy roots linked to a current one; actor_roots keeps only the roots the user works on (no "all" option); usage_area (requests or opportunities) keeps roots used in that module. Standard search, offset, limit and ids[]. Any authenticated user.',
    ],
];
