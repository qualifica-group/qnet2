<?php

return [
    'GET /product-category-nodes/for-select' => [
        'summary' => 'List all product category nodes for a dropdown',
        'description' => 'Returns every active product category, containers and leaves alike regardless of whether it is selectable on a line, as id and label (name), ordered by name. Used where a choice covers a whole branch, such as commission rules. Filtered by search (name contains), paginated with offset and limit; ids[] also returns those categories for display of a saved value. Response is a list (items + pagination). Requires authentication only.',
    ],
];
