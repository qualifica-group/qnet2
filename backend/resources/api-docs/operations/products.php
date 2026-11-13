<?php

return [
    'POST /products' => [
        'summary' => 'Create a product',
        'description' => 'Creates a catalogue product. name, category_id (an active, selectable product category) and product_type (SERVICE or PHYSICAL) are required; usages is a non-empty set of SALE, COST, INTERNAL (default SALE only, INTERNAL only on a PHYSICAL product). code is generated as PRD-0001... when omitted; missing price and cost default to 0, unit of measure and typology to the defaults. suppliers lists supplier registries with price, cost and one is_default; attribute_values are checked against the category attributes. Returns 201 with the product and the permissions block. Requires products.create.',
    ],
    'POST /products/activation' => [
        'summary' => 'Activate or deactivate many products',
        'description' => 'Sets is_active to the given value on up to 500 products (product_ids). Inactive products are no longer offered in product pickers. Each product gets its own activity log entry. Returns the number of updated products. Requires products.update and edit rights on the is_active field.',
    ],
    'POST /products/cost-type' => [
        'summary' => 'Assign the default cost type to many products',
        'description' => 'Sets the default cost type on up to 500 products (product_ids); cost_type_id must be an active cost type, or null to clear it (the key must be present). Each product gets its own activity log entry. Returns the number of updated products. Requires products.update.',
    ],
    'GET /products/for-select' => [
        'summary' => 'List products for a select',
        'description' => 'Returns active products of active categories as picker options, ordered by name, with code, description, price, cost, VAT rate, unit of measure, typology, cost type, category and suppliers. Filters: search (name or description), category_ids[], usage (SALE, COST or INTERNAL). Paginated with offset/limit (max 100); include_total=false skips the count and reports has_more instead. ids[] appends already-saved products outside the filters. Requires authentication only.',
    ],
    'GET /products/next-code' => [
        'summary' => 'Preview the next product code',
        'description' => 'Returns, as code, the next sequential product code (PRD-0001...) to prefill the create form. It is only a suggestion: the code is assigned for real when the product is created without one. Requires products.create.',
    ],
    'POST /products/revenue-type' => [
        'summary' => 'Assign the default revenue type to many products',
        'description' => 'Sets the default revenue sub-account on up to 500 products (ids); revenue_type_id must be an active revenue type, or null to clear it (the key must be present). Products whose value changed get their still unclassified invoice lines reclassified by a queued job. Returns the number of updated products. Requires products.update.',
    ],
    'GET /products/supplier-commissions' => [
        'summary' => 'Get the supplier commissions applicable to a product',
        'description' => 'For each of the given supplier_ids (supplier registries, max 50) returns the supplier commission that would apply today (configuration id, type, value, direction), or null. Pass product_id of an existing product, or category_id with optional product_typology_id for a product not yet saved (one of the two, not both). Requires one of products.viewAny, products.create, products.update, quotes.create, quotes.update, work-orders.manageCosts.',
    ],
    'GET /products/{product}' => [
        'summary' => 'Get a product',
        'description' => 'Returns the product with category, type and usages, prices, VAT rate, unit of measure, typology, cost and revenue types (including the effective revenue type), stock tracking and quantity, business function, suppliers (when the actor may see them), attribute values with the applicable attributes and their layout, custom fields and the permissions block. Requires products.view.',
    ],
    'PUT /products/{product}' => [
        'summary' => 'Update a product',
        'description' => 'Partially updates the product with the same fields as create; code is immutable (a different value answers 422). suppliers, when sent, replaces the supplier list ([] removes all); attribute_values are merged into the existing ones and validated against the category, including a category changed in the same request. Changing type or usages so that a product with stock is no longer stock-tracked answers 422. A changed revenue type queues the reclassification of its unclassified invoice lines. Requires products.update.',
    ],
    'DELETE /products/{product}' => [
        'summary' => 'Delete a product',
        'description' => 'Deletes the product. Refused with 409 when it has stock movements. Returns 204 no content. Requires products.delete.',
    ],
];
